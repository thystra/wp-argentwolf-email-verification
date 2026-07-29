=== ArgentWolf Email Verification ===
Contributors: wolfandraven
Tags: email verification, account activation, registration, user verification
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Require newly self-registered users to verify their email address before the WordPress account becomes active.

== Description ==

ArgentWolf Email Verification keeps newly self-registered WordPress accounts inactive until the account owner confirms the registered email address.

All verification is processed locally by WordPress and `wp_mail()`. The plugin does not use an external verification API and does not send registration data to a third-party verification service.

Features include:

* Existing accounts are preserved as verified on first activation.
* Administrator-created and WP-CLI-created accounts are automatically verified.
* New self-registered accounts are marked Pending.
* A one-time verification link is sent through `wp_mail()`.
* Pending users cannot sign in with a password or an Application Password.
* WordPress's normal new-user message is suppressed while pending.
* Users can request a replacement verification message without exposing whether an account exists.
* Administrators can view status, verify an account, or resend verification.
* Stale pending accounts can be removed automatically after a configurable number of days.
* Cleanup protects administrators and accounts that own WordPress content.
* Ordinary `wp_mail()` messages to pending users can be suppressed.
* Mixed-recipient messages continue to verified and outside recipients.
* Privacy-policy guidance and personal-data tools are included.
* A public verification-status API is available to compatible plugins.

ArgentWolf Email Verification does not replace SMTP, inbox-delivery monitoring, bounce handling, or newsletter subscriber management.

== Installation ==

1. Upload the `argentwolf-email-verification` folder to `/wp-content/plugins/`, or install the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate ArgentWolf Email Verification.
3. Open Settings > Email Verification.
4. Review pending-account retention and outbound-mail suppression.
5. Test registration and verification on a staging site before enabling public registration.

When upgrading from the former main filename, use a controlled replacement. Keep an administrator session open, back up the site, install and activate the canonical build, confirm existing account states, and remove any duplicate old plugin copy.

== Frequently Asked Questions ==

= Does the plugin verify whether a mailbox technically exists? =

No. It verifies that someone receiving mail at the address can use a one-time link. It does not query an external mailbox-validation service.

= Does the plugin send data to an external API? =

No. Verification is handled locally through WordPress and the site's configured `wp_mail()` transport.

= What happens to existing users when the plugin is first activated? =

Existing users are marked verified so activation does not lock them out.

= Which new users are automatically verified? =

Accounts created through WP-CLI, administrators, and accounts deliberately created by a logged-in user with permission to create users are trusted. Other new registrations are pending by default.

= Can pending users receive other WordPress emails? =

By default, normal `wp_mail()` messages to pending account addresses are suppressed. The verification message is always allowed. Mixed-recipient messages continue to other recipients.

= Does a successful wp_mail() result mean delivery occurred? =

No. WordPress only reports whether the mailer accepted the request. This plugin may also intentionally preempt a pending-only message and report it as handled. Compatible plugins should call the public verification API before sending.

= What if WP-Cron is disabled? =

Invoke WordPress cron from a real scheduler or WP-CLI so pending-account cleanup runs reliably.

= Where can I report issues or contribute? =

Use the project repository linked from the plugin settings page.

== Privacy ==

The plugin stores verification status, a keyed hash of the current one-time token, token expiration, verification-message time, and limited registration-workflow state in WordPress user metadata.

Token hashes are never included in personal-data exports. Privacy erasure removes expendable token and message metadata where safe, but retains verification status needed to prevent a pending account from becoming active through erasure.

Verification emails are sent through the site's configured `wp_mail()` transport. The privacy practices of that transport or mail provider are controlled by the site administrator.

== Developer API ==

`argentwolf_email_verification_is_user_verified( int $user_id ): bool`

Returns `true` only for an existing user whose account is not pending.

`argentwolf_email_verification_get_user_verification_status( int $user_id ): string`

Returns `verified`, `pending`, or `unknown`.

The plugin also emits canonical lifecycle actions for verification, cleanup, and pending-mail suppression. Legacy `wrav_ev_*` identifiers remain available during the compatibility period.

== Changelog ==

= 0.3.0 =

* Renamed the public plugin, main file, class, text domain, and documentation to ArgentWolf Email Verification.
* Added a stable public verification-status API for companion plugins.
* Added canonical lifecycle actions while retaining legacy aliases.
* Added a GitHub project and development-support link to the settings screen.
* Added privacy-policy, personal-data exporter, and eraser integration.
* Removed private infrastructure details from public documentation.
* Added release validation, packaging, architecture, and milestone documentation.
* Retained legacy option and user-metadata keys to preserve existing account state.

= 0.2.0 =

* Added configurable deletion of stale pending accounts.
* Added daily WP-Cron cleanup and a manual cleanup action.
* Added the settings screen and pending-account status.
* Added suppression and filtering of ordinary mail to pending accounts.
* Added safety exclusions for administrators and content owners.
* Added active-plugin upgrade handling.

= 0.1.0 =

* Initial test release.
