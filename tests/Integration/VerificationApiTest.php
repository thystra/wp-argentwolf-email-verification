<?php
/**
 * Verification API integration tests.
 *
 * File: tests/Integration/VerificationApiTest.php
 */

declare(strict_types=1);

/**
 * Exercise the public verification-provider contract against WordPress.
 */
final class VerificationApiTest extends WP_UnitTestCase {
	/**
	 * Create an administrator account that the plugin treats as trusted.
	 *
	 * @return int
	 */
	private function create_trusted_user(): int {
		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Public functions are loaded with the plugin.
	 *
	 * @return void
	 */
	public function test_public_api_functions_exist(): void {
		$this->assertTrue(
			function_exists(
				'argentwolf_email_verification_is_user_verified'
			)
		);
		$this->assertTrue(
			function_exists(
				'argentwolf_email_verification_get_user_verification_status'
			)
		);
	}

	/**
	 * Unknown users fail closed for companion integrations.
	 *
	 * @return void
	 */
	public function test_unknown_user_is_not_verified(): void {
		$user_id = 987654321;

		$this->assertFalse(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'unknown',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * Missing legacy metadata stays fail-open for an existing account.
	 *
	 * @return void
	 */
	public function test_existing_user_with_missing_meta_is_verified(): void {
		$user_id = $this->create_trusted_user();

		delete_user_meta( $user_id, '_wrav_ev_verified' );

		$this->assertTrue(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'verified',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * Explicit pending metadata is authoritative.
	 *
	 * @return void
	 */
	public function test_pending_user_is_not_verified(): void {
		$user_id = $this->create_trusted_user();

		update_user_meta( $user_id, '_wrav_ev_verified', '0' );

		$this->assertFalse(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'pending',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * Explicit verified metadata is authoritative.
	 *
	 * @return void
	 */
	public function test_verified_user_is_verified(): void {
		$user_id = $this->create_trusted_user();

		update_user_meta( $user_id, '_wrav_ev_verified', '1' );

		$this->assertTrue(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'verified',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * Deleted users become unknown immediately.
	 *
	 * @return void
	 */
	public function test_deleted_user_is_unknown(): void {
		$user_id = $this->create_trusted_user();

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->assertTrue( wp_delete_user( $user_id ) );

		$this->assertFalse(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'unknown',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * Administrators are auto-verified by the supported registration path.
	 *
	 * @return void
	 */
	public function test_administrator_is_auto_verified(): void {
		$user_id = $this->create_trusted_user();

		ArgentWolf_Email_Verification::handle_new_user( $user_id );

		$this->assertTrue(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
		$this->assertSame(
			'verified',
			argentwolf_email_verification_get_user_verification_status(
				$user_id
			)
		);
	}

	/**
	 * A successful transition emits the canonical verification action.
	 *
	 * @return void
	 */
	public function test_canonical_verified_action_is_emitted(): void {
		$user_id = $this->create_trusted_user();
		$calls   = 0;

		update_user_meta( $user_id, '_wrav_ev_verified', '0' );

		add_action(
			'argentwolf_email_verification_user_verified',
			static function ( int $verified_user_id ) use (
				$user_id,
				&$calls
			): void {
				if ( $user_id === $verified_user_id ) {
					++$calls;
				}
			}
		);

		$method = new ReflectionMethod(
			ArgentWolf_Email_Verification::class,
			'mark_verified'
		);
		$method->setAccessible( true );
		$method->invoke( null, $user_id );

		$this->assertSame( 1, $calls );
		$this->assertTrue(
			argentwolf_email_verification_is_user_verified( $user_id )
		);
	}
}

// EOF: tests/Integration/VerificationApiTest.php
