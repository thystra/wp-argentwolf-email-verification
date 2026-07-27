<?php
/**
 * Plugin Name: Wolf & Raven Local Email Verification
 * Description: Keeps newly self-registered accounts inactive until the user verifies their email address. Verification is processed locally using WordPress and wp_mail(); no third-party API is used.
 * Version: 0.2.0
 * Author: Wolf & Raven
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Text Domain: wolf-raven-email-verification
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WRAV_Local_Email_Verification {
	private const VERSION            = '0.2.0';
	private const OPTION_INITIALIZED = 'wrav_ev_initialized';
	private const OPTION_SETTINGS    = 'wrav_ev_settings';

	private const META_VERIFIED      = '_wrav_ev_verified';
	private const META_TOKEN_HASH    = '_wrav_ev_token_hash';
	private const META_TOKEN_EXPIRES = '_wrav_ev_token_expires';
	private const META_SENT_AT       = '_wrav_ev_sent_at';
	private const META_NATIVE_REG    = '_wrav_ev_native_registration';

	private const VERIFY_ACTION = 'wrav_verify_email';
	private const CRON_HOOK     = 'wrav_ev_daily_cleanup';
	private const SETTINGS_PAGE = 'wrav-email-verification';

	/** @var bool Permit the plugin's own verification message to reach a pending account. */
	private static $allow_pending_recipient_mail = false;

	/** @var array<string,bool> Request-local cache of pending-account email lookups. */
	private static $pending_email_cache = array();

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );

		add_action( 'user_register', array( __CLASS__, 'handle_new_user' ), 10, 2 );
		add_filter( 'wp_send_new_user_notification_to_user', array( __CLASS__, 'suppress_core_user_email' ), 10, 2 );

		add_filter( 'authenticate', array( __CLASS__, 'block_unverified_login' ), 100, 3 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( __CLASS__, 'block_application_passwords' ), 10, 2 );

		add_filter( 'pre_wp_mail', array( __CLASS__, 'preempt_pending_only_mail' ), 999, 2 );
		add_filter( 'wp_mail', array( __CLASS__, 'filter_pending_recipients' ), 999 );

		add_action( 'init', array( __CLASS__, 'handle_verification_link' ), 1 );
		add_filter( 'login_message', array( __CLASS__, 'filter_login_message' ) );
		add_action( 'login_form', array( __CLASS__, 'add_resend_link_to_login' ) );

		add_action( 'admin_post_nopriv_wrav_ev_resend', array( __CLASS__, 'handle_public_resend' ) );
		add_action( 'admin_post_wrav_ev_resend', array( __CLASS__, 'handle_public_resend' ) );

		add_filter( 'manage_users_columns', array( __CLASS__, 'add_users_column' ) );
		add_filter( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );
		add_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_actions' ), 10, 2 );
		add_action( 'admin_post_wrav_ev_admin_verify', array( __CLASS__, 'handle_admin_verify' ) );
		add_action( 'admin_post_wrav_ev_admin_resend', array( __CLASS__, 'handle_admin_resend' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_cleanup' ) );
		add_action( 'admin_post_wrav_ev_run_cleanup', array( __CLASS__, 'handle_manual_cleanup' ) );

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * On first activation, preserve access for every existing account. Every activation
	 * also ensures that defaults and the cleanup schedule exist.
	 */
	public static function activate(): void {
		if ( ! get_option( self::OPTION_INITIALIZED ) ) {
			$user_ids = get_users(
				array(
					'fields' => 'ids',
				)
			);

			foreach ( $user_ids as $user_id ) {
				update_user_meta( (int) $user_id, self::META_VERIFIED, '1' );
			}

			update_option(
				self::OPTION_INITIALIZED,
				array(
					'version'        => self::VERSION,
					'initialized_at' => time(),
				),
				false
			);
		}

		self::ensure_default_settings();
		self::schedule_cleanup();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Handles upgrades while the plugin remains active, because replacing an active
	 * plugin does not necessarily execute its activation hook again.
	 */
	public static function maybe_upgrade(): void {
		$state = get_option( self::OPTION_INITIALIZED );
		if ( ! is_array( $state ) ) {
			return;
		}

		self::ensure_default_settings();
		self::schedule_cleanup();

		if ( ! isset( $state['version'] ) || self::VERSION !== (string) $state['version'] ) {
			$state['version']      = self::VERSION;
			$state['upgraded_at']  = time();
			update_option( self::OPTION_INITIALIZED, $state, false );
		}
	}

	private static function default_settings(): array {
		return array(
			'cleanup_days'          => 7,
			'suppress_pending_mail' => 1,
		);
	}

	private static function ensure_default_settings(): void {
		$current = get_option( self::OPTION_SETTINGS, array() );
		$current = is_array( $current ) ? $current : array();
		$merged  = wp_parse_args( $current, self::default_settings() );

		if ( $merged !== $current ) {
			update_option( self::OPTION_SETTINGS, $merged, false );
		}
	}

	private static function settings(): array {
		$current = get_option( self::OPTION_SETTINGS, array() );
		$current = is_array( $current ) ? $current : array();
		return wp_parse_args( $current, self::default_settings() );
	}

	private static function cleanup_days(): int {
		$settings = self::settings();
		$days     = isset( $settings['cleanup_days'] ) ? absint( $settings['cleanup_days'] ) : 7;
		return min( 365, $days );
	}

	private static function suppress_pending_mail_enabled(): bool {
		$settings = self::settings();
		return ! empty( $settings['suppress_pending_mail'] );
	}

	private static function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Marks a newly created user pending unless the account was created by an administrator or WP-CLI.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $userdata Raw user data supplied to wp_insert_user().
	 */
	public static function handle_new_user( int $user_id, array $userdata = array() ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$auto_verify = self::should_auto_verify_new_user( $user );
		$auto_verify = (bool) apply_filters( 'wrav_ev_auto_verify_new_user', $auto_verify, $user, $userdata );

		if ( $auto_verify ) {
			self::mark_verified( $user_id );
			return;
		}

		update_user_meta( $user_id, self::META_VERIFIED, '0' );
		self::invalidate_pending_email_cache( $user );

		if ( self::is_native_wordpress_registration() ) {
			update_user_meta( $user_id, self::META_NATIVE_REG, '1' );
		}

		self::send_verification_email( $user_id, true );
	}

	private static function should_auto_verify_new_user( WP_User $user ): bool {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}

		// Accounts deliberately created by a logged-in administrator are trusted.
		if ( is_user_logged_in() && current_user_can( 'create_users' ) ) {
			return true;
		}

		return false;
	}

	private static function is_native_wordpress_registration(): bool {
		global $pagenow;

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'wp-login.php' === $pagenow && 'register' === $action;
	}

	/**
	 * Prevent the normal WordPress new-user message from being sent before verification.
	 */
	public static function suppress_core_user_email( bool $send, WP_User $user ): bool {
		if ( self::is_pending( $user->ID ) ) {
			return false;
		}

		return $send;
	}

	/**
	 * Block ordinary username/password authentication while an account is pending.
	 *
	 * @param WP_User|WP_Error|null $user     Current authentication result.
	 * @param string                $username Submitted username/email.
	 * @param string                $password Submitted password.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_unverified_login( $user, string $username, string $password ) {
		unset( $username, $password );

		if ( ! ( $user instanceof WP_User ) ) {
			return $user;
		}

		// Safety exception: an administrator can never be locked out by this plugin.
		if ( user_can( $user, 'manage_options' ) ) {
			return $user;
		}

		if ( ! self::is_pending( $user->ID ) ) {
			return $user;
		}

		$resend_url = add_query_arg( 'wrav_resend_form', '1', wp_login_url() );

		return new WP_Error(
			'wrav_email_not_verified',
			sprintf(
				/* translators: %s is a link to resend the verification email. */
				__( 'Your account is not active because its email address has not been verified. %s', 'wolf-raven-email-verification' ),
				'<a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend the verification email.', 'wolf-raven-email-verification' ) . '</a>'
			)
		);
	}

	public static function block_application_passwords( bool $available, WP_User $user ): bool {
		if ( self::is_pending( $user->ID ) && ! user_can( $user, 'manage_options' ) ) {
			return false;
		}

		return $available;
	}

	private static function is_pending( int $user_id ): bool {
		return '0' === (string) get_user_meta( $user_id, self::META_VERIFIED, true );
	}

	private static function is_verified( int $user_id ): bool {
		return ! self::is_pending( $user_id );
	}

	private static function mark_verified( int $user_id ): void {
		update_user_meta( $user_id, self::META_VERIFIED, '1' );
		delete_user_meta( $user_id, self::META_TOKEN_HASH );
		delete_user_meta( $user_id, self::META_TOKEN_EXPIRES );
		delete_user_meta( $user_id, self::META_SENT_AT );

		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User ) {
			self::invalidate_pending_email_cache( $user );
		}
	}

	private static function invalidate_pending_email_cache( WP_User $user ): void {
		$key = strtolower( trim( (string) $user->user_email ) );
		if ( '' !== $key ) {
			unset( self::$pending_email_cache[ $key ] );
		}
	}

	private static function token_hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private static function link_lifetime(): int {
		$seconds = (int) apply_filters( 'wrav_ev_link_lifetime', 48 * HOUR_IN_SECONDS );
		return max( HOUR_IN_SECONDS, $seconds );
	}

	private static function resend_cooldown(): int {
		$seconds = (int) apply_filters( 'wrav_ev_resend_cooldown', 5 * MINUTE_IN_SECONDS );
		return max( MINUTE_IN_SECONDS, $seconds );
	}

	/**
	 * Generate a fresh one-time token and send it through wp_mail().
	 */
	private static function send_verification_email( int $user_id, bool $ignore_cooldown = false ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || self::is_verified( $user_id ) ) {
			return false;
		}

		$last_sent = (int) get_user_meta( $user_id, self::META_SENT_AT, true );
		if ( ! $ignore_cooldown && $last_sent > 0 && ( time() - $last_sent ) < self::resend_cooldown() ) {
			return false;
		}

		try {
			$token = bin2hex( random_bytes( 32 ) );
		} catch ( Exception $exception ) {
			error_log( 'Wolf & Raven Email Verification: secure token generation failed: ' . $exception->getMessage() );
			return false;
		}

		$expires = time() + self::link_lifetime();

		update_user_meta( $user_id, self::META_TOKEN_HASH, self::token_hash( $token ) );
		update_user_meta( $user_id, self::META_TOKEN_EXPIRES, (string) $expires );
		update_user_meta( $user_id, self::META_SENT_AT, (string) time() );

		$verification_url = add_query_arg(
			array(
				'wrav_ev_action' => self::VERIFY_ACTION,
				'user_id'        => $user_id,
				'token'          => $token,
			),
			home_url( '/' )
		);

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$hours     = (int) ceil( self::link_lifetime() / HOUR_IN_SECONDS );

		$subject = sprintf(
			/* translators: %s is the website name. */
			__( '[%s] Verify your email address', 'wolf-raven-email-verification' ),
			$site_name
		);

		$message  = sprintf( __( "Hello %s,", 'wolf-raven-email-verification' ), $user->display_name ?: $user->user_login ) . "\n\n";
		$message .= sprintf(
			/* translators: %s is the website name. */
			__( 'Thank you for registering at %s. Your account is currently inactive.', 'wolf-raven-email-verification' ),
			$site_name
		) . "\n\n";
		$message .= __( 'Use the link below to verify your email address and activate your account:', 'wolf-raven-email-verification' ) . "\n\n";
		$message .= $verification_url . "\n\n";
		$message .= sprintf(
			/* translators: %d is the number of hours before the link expires. */
			_n( 'This link expires in %d hour.', 'This link expires in %d hours.', $hours, 'wolf-raven-email-verification' ),
			$hours
		) . "\n\n";
		$message .= __( 'If you did not create this account, you can ignore this message.', 'wolf-raven-email-verification' ) . "\n";

		$subject = (string) apply_filters( 'wrav_ev_email_subject', $subject, $user, $verification_url );
		$message = (string) apply_filters( 'wrav_ev_email_message', $message, $user, $verification_url, $expires );

		self::$allow_pending_recipient_mail = true;
		try {
			$sent = wp_mail( $user->user_email, $subject, $message );
		} finally {
			self::$allow_pending_recipient_mail = false;
		}

		if ( ! $sent ) {
			error_log( sprintf( 'Wolf & Raven Email Verification: wp_mail() failed for user ID %d.', $user_id ) );
		}

		return $sent;
	}

	/**
	 * Short-circuit a message when every recognized recipient belongs to a pending
	 * account. Returning true records the message as intentionally handled and avoids
	 * repeated retries by callers that treat false as a transport failure.
	 *
	 * @param null|bool $return Existing preemption value.
	 * @param array     $atts   wp_mail() arguments.
	 * @return null|bool
	 */
	public static function preempt_pending_only_mail( $return, array $atts ) {
		if ( null !== $return || self::$allow_pending_recipient_mail || ! self::suppress_pending_mail_enabled() ) {
			return $return;
		}

		$analysis = self::analyze_mail_recipients( $atts );
		if ( $analysis['has_unknown'] || empty( $analysis['emails'] ) ) {
			return $return;
		}

		$pending = 0;
		$allowed = 0;
		foreach ( array_unique( $analysis['emails'] ) as $email ) {
			if ( self::email_belongs_to_pending_user( $email ) ) {
				++$pending;
			} else {
				++$allowed;
			}
		}

		if ( $pending > 0 && 0 === $allowed ) {
			do_action( 'wrav_ev_mail_suppressed', $atts, $analysis['emails'] );
			return true;
		}

		return $return;
	}

	/**
	 * Remove pending account addresses from mixed-recipient messages while allowing
	 * the message to continue to verified users, administrators, and outside addresses.
	 */
	public static function filter_pending_recipients( array $args ): array {
		if ( self::$allow_pending_recipient_mail || ! self::suppress_pending_mail_enabled() ) {
			return $args;
		}

		if ( array_key_exists( 'to', $args ) ) {
			$args['to'] = self::remove_pending_recipients( $args['to'] );
		}

		if ( array_key_exists( 'headers', $args ) ) {
			$args['headers'] = self::remove_pending_header_recipients( $args['headers'] );
		}

		return $args;
	}

	/**
	 * @param array $atts wp_mail() arguments.
	 * @return array{emails:array<int,string>,has_unknown:bool}
	 */
	private static function analyze_mail_recipients( array $atts ): array {
		$analysis = array(
			'emails'      => array(),
			'has_unknown' => false,
		);

		if ( isset( $atts['to'] ) ) {
			self::append_recipient_analysis( $atts['to'], $analysis );
		}

		if ( isset( $atts['headers'] ) ) {
			foreach ( self::header_lines( $atts['headers'] ) as $line ) {
				if ( preg_match( '/^\s*(cc|bcc)\s*:\s*(.+)$/i', $line, $matches ) ) {
					self::append_recipient_analysis( $matches[2], $analysis );
				}
			}
		}

		return $analysis;
	}

	/**
	 * @param string|string[]                         $recipients Recipient value.
	 * @param array{emails:array<int,string>,has_unknown:bool} $analysis Analysis accumulator.
	 */
	private static function append_recipient_analysis( $recipients, array &$analysis ): void {
		foreach ( self::recipient_entries( $recipients ) as $entry ) {
			$email = self::extract_email_address( $entry );
			if ( '' === $email ) {
				if ( '' !== trim( $entry ) ) {
					$analysis['has_unknown'] = true;
				}
				continue;
			}

			$analysis['emails'][] = $email;
		}
	}

	/**
	 * @param string|string[] $recipients Recipient value.
	 * @return string[]
	 */
	private static function recipient_entries( $recipients ): array {
		$entries = array();
		$items   = is_array( $recipients ) ? $recipients : array( $recipients );

		foreach ( $items as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$parsed = str_getcsv( (string) $item, ',', '"', '\\' );
			foreach ( $parsed as $entry ) {
				$entry = trim( (string) $entry );
				if ( '' !== $entry ) {
					$entries[] = $entry;
				}
			}
		}

		return $entries;
	}

	private static function extract_email_address( string $recipient ): string {
		$recipient = trim( $recipient );
		if ( is_email( $recipient ) ) {
			return strtolower( $recipient );
		}

		if ( preg_match( '/<\s*([^<>]+)\s*>/', $recipient, $matches ) && is_email( trim( $matches[1] ) ) ) {
			return strtolower( trim( $matches[1] ) );
		}

		if ( preg_match( '/([a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,})/i', $recipient, $matches ) && is_email( $matches[1] ) ) {
			return strtolower( $matches[1] );
		}

		return '';
	}

	/**
	 * @param string|string[] $recipients Recipient value.
	 * @return string[]
	 */
	private static function remove_pending_recipients( $recipients ): array {
		$allowed = array();
		foreach ( self::recipient_entries( $recipients ) as $entry ) {
			$email = self::extract_email_address( $entry );
			if ( '' === $email || ! self::email_belongs_to_pending_user( $email ) ) {
				$allowed[] = $entry;
			}
		}
		return $allowed;
	}

	/**
	 * @param string|string[] $headers Header value.
	 * @return string|string[]
	 */
	private static function remove_pending_header_recipients( $headers ) {
		$was_array = is_array( $headers );
		$filtered  = array();

		foreach ( self::header_lines( $headers ) as $line ) {
			if ( preg_match( '/^\s*(cc|bcc)\s*:\s*(.+)$/i', $line, $matches ) ) {
				$allowed = self::remove_pending_recipients( $matches[2] );
				if ( ! empty( $allowed ) ) {
					$filtered[] = ucfirst( strtolower( $matches[1] ) ) . ': ' . implode( ', ', $allowed );
				}
				continue;
			}

			$filtered[] = $line;
		}

		return $was_array ? $filtered : implode( "\r\n", $filtered );
	}

	/**
	 * @param string|string[] $headers Header value.
	 * @return string[]
	 */
	private static function header_lines( $headers ): array {
		if ( is_array( $headers ) ) {
			return array_values(
				array_filter(
					array_map( 'strval', $headers ),
					static function ( string $line ): bool {
						return '' !== trim( $line );
					}
				)
			);
		}

		if ( ! is_scalar( $headers ) || '' === trim( (string) $headers ) ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', (string) $headers );
		return is_array( $lines ) ? array_values( array_filter( $lines, 'strlen' ) ) : array();
	}

	private static function email_belongs_to_pending_user( string $email ): bool {
		$key = strtolower( trim( $email ) );
		if ( array_key_exists( $key, self::$pending_email_cache ) ) {
			return self::$pending_email_cache[ $key ];
		}

		$user = get_user_by( 'email', $key );
		self::$pending_email_cache[ $key ] = $user instanceof WP_User && self::is_pending( $user->ID );
		return self::$pending_email_cache[ $key ];
	}

	public static function handle_verification_link(): void {
		$action = isset( $_GET['wrav_ev_action'] ) ? sanitize_key( wp_unslash( $_GET['wrav_ev_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::VERIFY_ACTION !== $action ) {
			return;
		}

		nocache_headers();

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token   = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user || '' === $token ) {
			self::redirect_verification_result( 'invalid' );
		}

		if ( self::is_verified( $user_id ) ) {
			self::redirect_after_verification( $user, 'already' );
		}

		$stored_hash = (string) get_user_meta( $user_id, self::META_TOKEN_HASH, true );
		$expires     = (int) get_user_meta( $user_id, self::META_TOKEN_EXPIRES, true );

		if ( '' === $stored_hash || $expires < time() ) {
			delete_user_meta( $user_id, self::META_TOKEN_HASH );
			delete_user_meta( $user_id, self::META_TOKEN_EXPIRES );
			self::redirect_verification_result( 'expired' );
		}

		if ( ! hash_equals( $stored_hash, self::token_hash( $token ) ) ) {
			self::redirect_verification_result( 'invalid' );
		}

		self::mark_verified( $user_id );
		do_action( 'wrav_ev_user_verified', $user_id, $user );

		self::redirect_after_verification( $user, 'verified' );
	}

	private static function redirect_after_verification( WP_User $user, string $result ): void {
		$native_registration = '1' === (string) get_user_meta( $user->ID, self::META_NATIVE_REG, true );
		delete_user_meta( $user->ID, self::META_NATIVE_REG );

		if ( $native_registration && 'already' !== $result ) {
			$password_key = get_password_reset_key( $user );
			if ( ! is_wp_error( $password_key ) ) {
				$url = network_site_url(
					'wp-login.php?action=rp&key=' . rawurlencode( $password_key ) . '&login=' . rawurlencode( $user->user_login ),
					'login'
				);
				$url = add_query_arg( 'wrav_verified', '1', $url );
				$url = (string) apply_filters( 'wrav_ev_after_verification_url', $url, $user, $result );
				wp_safe_redirect( $url );
				exit;
			}
		}

		$url = add_query_arg( 'wrav_verified', rawurlencode( $result ), wp_login_url() );
		$url = (string) apply_filters( 'wrav_ev_after_verification_url', $url, $user, $result );
		wp_safe_redirect( $url );
		exit;
	}

	private static function redirect_verification_result( string $result ): void {
		$url = add_query_arg(
			array(
				'wrav_verification' => rawurlencode( $result ),
				'wrav_resend_form'  => '1',
			),
			wp_login_url()
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function filter_login_message( string $message ): string {
		$verified = isset( $_GET['wrav_verified'] ) ? sanitize_key( wp_unslash( $_GET['wrav_verified'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result   = isset( $_GET['wrav_verification'] ) ? sanitize_key( wp_unslash( $_GET['wrav_verification'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$resent   = isset( $_GET['wrav_resent'] ) ? sanitize_key( wp_unslash( $_GET['wrav_resent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$resend   = isset( $_GET['wrav_resend_form'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wrav_resend_form'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$check    = isset( $_GET['checkemail'] ) ? sanitize_key( wp_unslash( $_GET['checkemail'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'registered' === $check ) {
			$message .= '<div class="message"><p>' . esc_html__( 'Your account has been created but is not active yet. Check your email for the verification link.', 'wolf-raven-email-verification' ) . '</p></div>';
		}

		if ( '1' === $verified || 'verified' === $verified ) {
			$message .= '<div class="message"><p>' . esc_html__( 'Your email address has been verified and your account is now active. Set your password or log in to continue.', 'wolf-raven-email-verification' ) . '</p></div>';
		} elseif ( 'already' === $verified ) {
			$message .= '<div class="message"><p>' . esc_html__( 'This account is already verified. You may log in.', 'wolf-raven-email-verification' ) . '</p></div>';
		}

		if ( 'expired' === $result ) {
			$message .= '<div id="login_error"><p>' . esc_html__( 'That verification link has expired. Request a new link below.', 'wolf-raven-email-verification' ) . '</p></div>';
		} elseif ( 'invalid' === $result ) {
			$message .= '<div id="login_error"><p>' . esc_html__( 'That verification link is invalid or has already been replaced. Request a new link below.', 'wolf-raven-email-verification' ) . '</p></div>';
		}

		if ( '1' === $resent ) {
			$message .= '<div class="message"><p>' . esc_html__( 'If a pending account matches that username or email address, a new verification message has been sent. Please also check the spam folder.', 'wolf-raven-email-verification' ) . '</p></div>';
		}

		if ( $resend ) {
			$message .= self::resend_form_html();
		}

		return $message;
	}

	private static function resend_form_html(): string {
		$action_url = admin_url( 'admin-post.php' );
		$nonce      = wp_nonce_field( 'wrav_ev_resend', 'wrav_ev_nonce', true, false );

		$html  = '<div class="message wrav-ev-resend-form">';
		$html .= '<p><strong>' . esc_html__( 'Resend verification email', 'wolf-raven-email-verification' ) . '</strong></p>';
		$html .= '<form method="post" action="' . esc_url( $action_url ) . '">';
		$html .= '<input type="hidden" name="action" value="wrav_ev_resend">';
		$html .= $nonce;
		$html .= '<p><label for="wrav_ev_login">' . esc_html__( 'Username or email address', 'wolf-raven-email-verification' ) . '</label><br>';
		$html .= '<input type="text" name="user_login" id="wrav_ev_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username" required></p>';
		$html .= '<p class="submit"><button type="submit" class="button button-primary button-large">' . esc_html__( 'Send verification email', 'wolf-raven-email-verification' ) . '</button></p>';
		$html .= '</form></div>';

		return $html;
	}

	public static function add_resend_link_to_login(): void {
		$resend_url = add_query_arg( 'wrav_resend_form', '1', wp_login_url() );
		echo '<p style="margin-top:12px"><a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend verification email', 'wolf-raven-email-verification' ) . '</a></p>';
	}

	public static function handle_public_resend(): void {
		$nonce = isset( $_POST['wrav_ev_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wrav_ev_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wrav_ev_resend' ) ) {
			wp_safe_redirect( add_query_arg( 'wrav_resent', '1', wp_login_url() ) );
			exit;
		}

		$login = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
		$user  = false;

		if ( '' !== $login ) {
			$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );
		}

		if ( $user instanceof WP_User && self::is_pending( $user->ID ) ) {
			self::send_verification_email( $user->ID, false );
		}

		// Always use the same response to avoid revealing whether an account exists.
		wp_safe_redirect( add_query_arg( 'wrav_resent', '1', wp_login_url() ) );
		exit;
	}

	public static function add_users_column( array $columns ): array {
		$columns['wrav_ev_status'] = __( 'Email verification', 'wolf-raven-email-verification' );
		return $columns;
	}

	public static function render_users_column( string $output, string $column_name, int $user_id ): string {
		if ( 'wrav_ev_status' !== $column_name ) {
			return $output;
		}

		if ( self::is_verified( $user_id ) ) {
			return '<span style="color:#008a20;font-weight:600">' . esc_html__( 'Verified', 'wolf-raven-email-verification' ) . '</span>';
		}

		return '<span style="color:#b32d2e;font-weight:600">' . esc_html__( 'Pending', 'wolf-raven-email-verification' ) . '</span>';
	}

	public static function add_user_row_actions( array $actions, WP_User $user ): array {
		if ( self::is_verified( $user->ID ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return $actions;
		}

		$verify_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'wrav_ev_admin_verify',
					'user_id' => $user->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'wrav_ev_admin_verify_' . $user->ID
		);

		$resend_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'wrav_ev_admin_resend',
					'user_id' => $user->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'wrav_ev_admin_resend_' . $user->ID
		);

		$actions['wrav_ev_verify'] = '<a href="' . esc_url( $verify_url ) . '">' . esc_html__( 'Verify email', 'wolf-raven-email-verification' ) . '</a>';
		$actions['wrav_ev_resend'] = '<a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend verification', 'wolf-raven-email-verification' ) . '</a>';

		return $actions;
	}

	public static function handle_admin_verify(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		check_admin_referer( 'wrav_ev_admin_verify_' . $user_id );

		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to verify this account.', 'wolf-raven-email-verification' ) );
		}

		self::mark_verified( $user_id );
		delete_user_meta( $user_id, self::META_NATIVE_REG );

		self::admin_redirect( 'verified' );
	}

	public static function handle_admin_resend(): void {
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		check_admin_referer( 'wrav_ev_admin_resend_' . $user_id );

		if ( ! $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'You are not allowed to resend verification for this account.', 'wolf-raven-email-verification' ) );
		}

		self::send_verification_email( $user_id, true );
		self::admin_redirect( 'resent' );
	}

	private static function admin_redirect( string $notice, array $extra_args = array() ): void {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = admin_url( 'users.php' );
		}

		$args = array_merge( array( 'wrav_ev_admin_notice' => $notice ), $extra_args );
		wp_safe_redirect( add_query_arg( $args, $referer ) );
		exit;
	}

	public static function run_scheduled_cleanup(): void {
		self::cleanup_expired_pending_users();
	}

	/**
	 * Delete stale pending accounts in bounded batches. Administrators and any pending
	 * account that owns WordPress posts are skipped for safety.
	 *
	 * @return array{deleted:int,skipped:int}
	 */
	private static function cleanup_expired_pending_users(): array {
		$result = array(
			'deleted' => 0,
			'skipped' => 0,
		);

		$days = self::cleanup_days();
		if ( $days < 1 ) {
			return $result;
		}

		$batch_size = (int) apply_filters( 'wrav_ev_cleanup_batch_size', 500 );
		$batch_size = max( 1, min( 5000, $batch_size ) );
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		global $wpdb;
		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT u.ID
				FROM {$wpdb->users} AS u
				INNER JOIN {$wpdb->usermeta} AS um ON um.user_id = u.ID
				WHERE um.meta_key = %s
				  AND um.meta_value = '0'
				  AND u.user_registered <= %s
				ORDER BY u.ID ASC
				LIMIT %d",
				self::META_VERIFIED,
				$cutoff,
				$batch_size
			)
		);

		if ( empty( $user_ids ) ) {
			return $result;
		}

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			$user    = get_userdata( $user_id );
			if ( ! ( $user instanceof WP_User ) || ! self::is_pending( $user_id ) ) {
				continue;
			}

			if ( user_can( $user, 'manage_options' ) ) {
				self::mark_verified( $user_id );
				++$result['skipped'];
				continue;
			}

			$owns_content = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->posts} WHERE post_author = %d LIMIT 1",
					$user_id
				)
			);

			if ( $owns_content ) {
				++$result['skipped'];
				do_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'owns_content' );
				continue;
			}

			$should_delete = (bool) apply_filters( 'wrav_ev_should_delete_pending_user', true, $user, $cutoff );
			if ( ! $should_delete ) {
				++$result['skipped'];
				do_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'filtered' );
				continue;
			}

			if ( wp_delete_user( $user_id ) ) {
				++$result['deleted'];
				do_action( 'wrav_ev_pending_user_deleted', $user_id, $user );
			} else {
				++$result['skipped'];
			}
		}

		return $result;
	}

	public static function handle_manual_cleanup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run pending-account cleanup.', 'wolf-raven-email-verification' ) );
		}

		check_admin_referer( 'wrav_ev_run_cleanup' );
		$result = self::cleanup_expired_pending_users();

		$url = add_query_arg(
			array(
				'page'                 => self::SETTINGS_PAGE,
				'wrav_ev_admin_notice' => 'cleanup',
				'wrav_ev_deleted'      => $result['deleted'],
				'wrav_ev_skipped'      => $result['skipped'],
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private static function pending_account_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '0'",
				self::META_VERIFIED
			)
		);
	}

	public static function register_settings(): void {
		register_setting(
			'wrav_ev_settings_group',
			self::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);

		add_settings_section(
			'wrav_ev_cleanup_section',
			__( 'Pending account handling', 'wolf-raven-email-verification' ),
			array( __CLASS__, 'render_settings_section' ),
			self::SETTINGS_PAGE
		);

		add_settings_field(
			'wrav_ev_cleanup_days',
			__( 'Delete pending accounts after', 'wolf-raven-email-verification' ),
			array( __CLASS__, 'render_cleanup_days_field' ),
			self::SETTINGS_PAGE,
			'wrav_ev_cleanup_section'
		);

		add_settings_field(
			'wrav_ev_suppress_pending_mail',
			__( 'Other outbound email', 'wolf-raven-email-verification' ),
			array( __CLASS__, 'render_suppress_mail_field' ),
			self::SETTINGS_PAGE,
			'wrav_ev_cleanup_section'
		);
	}

	public static function sanitize_settings( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$days  = isset( $input['cleanup_days'] ) ? absint( $input['cleanup_days'] ) : 7;

		return array(
			'cleanup_days'          => min( 365, $days ),
			'suppress_pending_mail' => empty( $input['suppress_pending_mail'] ) ? 0 : 1,
		);
	}

	public static function add_settings_page(): void {
		add_options_page(
			__( 'Email Verification', 'wolf-raven-email-verification' ),
			__( 'Email Verification', 'wolf-raven-email-verification' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function add_plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE ) ) . '">' . esc_html__( 'Settings', 'wolf-raven-email-verification' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Control how long unverified registrations remain and whether normal site email may be sent to pending account addresses.', 'wolf-raven-email-verification' ) . '</p>';
	}

	public static function render_cleanup_days_field(): void {
		$days = self::cleanup_days();
		echo '<input type="number" min="0" max="365" step="1" name="' . esc_attr( self::OPTION_SETTINGS ) . '[cleanup_days]" value="' . esc_attr( (string) $days ) . '" class="small-text"> ';
		echo esc_html__( 'days', 'wolf-raven-email-verification' );
		echo '<p class="description">' . esc_html__( 'Recommended: 7 days. Enter 0 to disable automatic deletion. Cleanup runs daily and skips administrators and accounts that own WordPress content.', 'wolf-raven-email-verification' ) . '</p>';
	}

	public static function render_suppress_mail_field(): void {
		$enabled = self::suppress_pending_mail_enabled();
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_SETTINGS ) . '[suppress_pending_mail]" value="1" ' . checked( $enabled, true, false ) . '> ';
		echo esc_html__( 'Suppress normal wp_mail() messages to pending account addresses', 'wolf-raven-email-verification' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'The verification message is always allowed. In mixed-recipient mail, pending addresses are removed while verified or outside recipients still receive the message.', 'wolf-raven-email-verification' ) . '</p>';
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$pending  = self::pending_account_count();
		$next_run = wp_next_scheduled( self::CRON_HOOK );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Email Verification', 'wolf-raven-email-verification' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wrav_ev_settings_group' );
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>

			<hr>
			<h2><?php echo esc_html__( 'Cleanup status', 'wolf-raven-email-verification' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d is the number of currently pending accounts. */
						_n( '%d account is currently pending.', '%d accounts are currently pending.', $pending, 'wolf-raven-email-verification' ),
						$pending
					)
				);
				?>
			</p>
			<p>
				<?php
				if ( $next_run ) {
					echo esc_html(
						sprintf(
							/* translators: %s is a localized date and time. */
							__( 'Next scheduled cleanup: %s', 'wolf-raven-email-verification' ),
							wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run )
						)
					);
				} else {
					echo esc_html__( 'No cleanup event is currently scheduled.', 'wolf-raven-email-verification' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wrav_ev_run_cleanup">
				<?php wp_nonce_field( 'wrav_ev_run_cleanup' ); ?>
				<?php submit_button( __( 'Run cleanup now', 'wolf-raven-email-verification' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function admin_notices(): void {
		$notice = isset( $_GET['wrav_ev_admin_notice'] ) ? sanitize_key( wp_unslash( $_GET['wrav_ev_admin_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'verified' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The account has been manually verified and activated.', 'wolf-raven-email-verification' ) . '</p></div>';
		} elseif ( 'resent' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'A fresh verification email was requested for the account.', 'wolf-raven-email-verification' ) . '</p></div>';
		} elseif ( 'cleanup' === $notice ) {
			$deleted = isset( $_GET['wrav_ev_deleted'] ) ? absint( $_GET['wrav_ev_deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped = isset( $_GET['wrav_ev_skipped'] ) ? absint( $_GET['wrav_ev_skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$text    = sprintf(
				/* translators: 1: number deleted, 2: number skipped. */
				__( 'Pending-account cleanup completed: %1$d deleted, %2$d skipped.', 'wolf-raven-email-verification' ),
				$deleted,
				$skipped
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
		}
	}
}

register_activation_hook( __FILE__, array( 'WRAV_Local_Email_Verification', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WRAV_Local_Email_Verification', 'deactivate' ) );
WRAV_Local_Email_Verification::init();
