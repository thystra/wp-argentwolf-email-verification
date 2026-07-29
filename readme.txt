=== ArgentWolf Email Verification ===
Contributors: wolfandraven
Tags: email verification, account activation, registration, user verification
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps newly self-registered WordPress accounts inactive until the owner verifies the registered email address.

== Description ==

ArgentWolf Email Verification provides local, self-hosted email verification for newly registered WordPress users.

The plugin does not call an external email-verification API. It creates a one-time verification link locally and sends the message through WordPress `wp_mail()` and the site's configured mail transport.

Core behavior:

* Existing accounts are preserved as verified when the plugin is first activated.
* Accounts created deliberately by a logged-in administrator or WP-CLI are automatically verified.
* Other newly registered accounts are marked Pending.
* Pending users cannot authenticate with a normal password or an Application Password.
* WordPress's normal new-user email is suppressed while an account is pending.
* Users can request another verification message without disclosing whether an account exists.
* Administrators can view verification status, resend verification, or verify an account manually.
* Pending accounts can be removed automatically after a configurable retention period.
* Administrators and pending users who own WordPress content are not removed by cleanup.
* Ordinary `wp_mail()` messages to pending account addresses can be suppressed.
* Mixed-recipient messages continue to verified users and outside addresses after pending addresses are removed.
* Verification status is available to compatible plugins through a public API.

The plugin does not prove that a mailbox exists without sending a message, replace SMTP service, process bounces, or guarantee inbox delivery.

== Installation ==

1. Upload the `argentwolf-email-verification` directory to `/wp-content/plugins/`, or install the release ZIP through the WordPress Plugins screen.
2. Activate **ArgentWolf Email Verification**.
3. Open **Settings > Email Verification**.
4. Review pending-account retention and outbound-email suppression.
5. Test registration and verification using an address you control.

== Frequently Asked Questions ==

= Does the plugin use an external verification service? =

No. Token creation and verification are processed locally by WordPress. Messages are sent through `wp_mail()`.

= Which users become pending? =

New self-registrations become pending. Accounts created by WP-CLI, administrators, or a logged-in user who can create users are trusted and automatically verified.

= What happens before verification? =

The account cannot authenticate with a normal password or an Application Password. Ordinary outbound messages to the pending address are suppressed by default.

= Can an administrator verify a user manually? =

Yes. Pending users have Verify and Resend actions on the WordPress Users screen.

= Can pending accounts be deleted automatically? =

Yes. Cleanup runs daily through WP-Cron. The default retention is seven days and can be changed from zero to 365 days. Setting it to zero disables deletion.

Cleanup skips administrators, users who own WordPress content, and users excluded by the cleanup filter.

= Does a successful wp_mail() result prove delivery? =

No. It only means WordPress handed the message to the configured mailer without an immediate error.

= Does the plugin support another plugin checking verification status? =

Yes. Use the public functions:

`argentwolf_email_verification_is_user_verified( int $user_id ): bool`

`argentwolf_email_verification_get_user_verification_status( int $user_id ): string`

The status function returns `verified`, `pending`, or `unknown`.

== Settings ==

The settings page is under **Settings > Email Verification**.

= Delete pending accounts after =

Default: seven days. Enter zero to disable automatic deletion. Valid range: zero to 365 days.

= Other outbound email =

Enabled by default. Normal `wp_mail()` messages to pending account addresses are suppressed. This cannot intercept another plugin that bypasses `wp_mail()` and sends through its own transport or remote API.

= Cleanup status =

Displays the pending-account count and the next scheduled cleanup. Administrators can also run cleanup manually.

== Privacy ==

The plugin stores verification status and limited verification-workflow metadata in WordPress user metadata.

Raw verification tokens are not stored. The plugin stores a keyed token hash, expiration time, message-request time, and limited registration-workflow state.

The plugin includes suggested privacy-policy text and WordPress personal-data exporter and eraser integration. Token and message metadata can be erased, but verification status is retained because removing it could alter account-access security.

== Security ==

* Verification tokens contain 256 bits of cryptographically secure randomness.
* Only an HMAC-SHA256 token hash is stored.
* Verification links expire after 48 hours by default.
* Requesting a new link invalidates the previous link.
* Public resend requests are throttled.
* Public responses do not disclose whether an account exists.
* Administrators are protected from accidental lockout.
* Accounts without an explicit Pending marker are treated as verified to preserve established access during upgrades or temporary interruptions.

== Developer API ==

Canonical filters and actions use the `argentwolf_email_verification_` prefix. Selected legacy `wrav_ev_*` aliases remain for compatibility.

Important filters:

* `argentwolf_email_verification_link_lifetime`
* `argentwolf_email_verification_resend_cooldown`
* `argentwolf_email_verification_cleanup_batch_size`
* `argentwolf_email_verification_auto_verify_new_user`
* `argentwolf_email_verification_email_subject`
* `argentwolf_email_verification_email_message`
* `argentwolf_email_verification_after_verification_url`
* `argentwolf_email_verification_should_delete_pending_user`

Important actions:

* `argentwolf_email_verification_user_verified`
* `argentwolf_email_verification_pending_user_deleted`
* `argentwolf_email_verification_pending_user_cleanup_skipped`
* `argentwolf_email_verification_mail_suppressed`
* `argentwolf_email_verification_error`

The error action receives a stable error code and a context array. It lets a logging or monitoring integration record operational failures without the plugin writing directly to the PHP error log.

== Upgrade Notice ==

= 0.3.2 =

Maintenance release documenting two intentional, bounded user-meta queries for WordPress Plugin Check.

= 0.3.1 =

Maintenance release for WordPress Plugin Check compliance and WordPress.org submission preparation.

== Changelog ==

= 0.3.2 =

* Documented and narrowly suppressed two intentional Plugin Check slow-query warnings.
* The affected queries remain bounded and are used only for daily pending-account cleanup and the administrative pending-account count.
* No verification, login, email, cleanup, settings, or data-storage behavior changed.

= 0.3.1 =

* Added the missing translator explanation for the verification greeting placeholder.
* Replaced direct PHP error-log writes with an integration action for operational errors.
* Replaced direct database queries with WordPress user and post query APIs.
* Corrected WordPress.org readme branding, version, and public documentation.
* Preserved existing verification settings, user metadata, hooks, and behavior.

= 0.3.0 =

* Standardized the public name, slug, text domain, main file, and integration API as ArgentWolf Email Verification.
* Added public verification-status functions for companion plugins.
* Added canonical lifecycle actions while retaining selected legacy aliases.
* Added WordPress privacy-policy, exporter, and eraser integration.
* Added repository documentation, validation tooling, and deterministic release packaging.
* Added a support-development link to the settings page and plugin action row.
* Preserved existing `wrav_ev_*` settings and user metadata for upgrade compatibility.

= 0.2.0 =

* Added configurable deletion of stale pending accounts.
* Added daily WP-Cron cleanup and a manual cleanup action.
* Added settings for retention and pending-recipient mail suppression.
* Added mixed-recipient filtering and cleanup safety exclusions.

= 0.1.0 =

* Initial test release.
