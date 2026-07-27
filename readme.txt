=== Wolf & Raven Local Email Verification ===
Contributors: wolfandraven
Tags: email verification, account activation, registration
Requires at least: 6.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Keeps newly self-registered WordPress accounts inactive until the owner clicks a one-time email verification link. All processing occurs locally in WordPress and through wp_mail(). The plugin makes no API calls and sends no registration data to a third-party verification service.

== Behavior ==

* Existing users are marked verified on the plugin's first activation.
* Accounts created by a logged-in administrator or WP-CLI are automatically verified.
* New self-registered accounts are marked Pending.
* WordPress's normal new-user email is suppressed while the account is pending.
* A one-time verification link is sent through wp_mail().
* Pending users cannot authenticate with a normal password or an Application Password.
* Native WordPress registrants are taken directly to the Set Password screen after verification.
* Other registration forms are taken to the login page after verification.
* Users can request another verification email without revealing whether an account exists.
* Administrators can see Pending/Verified status in Users and can manually verify or resend.
* Pending accounts are deleted after 7 days by default; the retention period is configurable from 0 to 365 days.
* Cleanup runs daily through WP-Cron, is available manually, and skips administrators and accounts that own WordPress content.
* Normal wp_mail() messages to pending account addresses are suppressed by default.
* In mixed-recipient messages, pending addresses are removed while other recipients still receive the message.
* The plugin's own verification email bypasses pending-recipient suppression.

== Upgrade from 0.1.0 ==

1. In WordPress, open Plugins > Add New > Upload Plugin.
2. Upload wolf-raven-email-verification-0.2.0.zip.
3. Choose Replace current with uploaded when WordPress detects the existing plugin.
4. The plugin remains configured for the existing verified and pending accounts.
5. Open Settings > Email Verification and review the new settings.

No deactivate/reactivate step is required. The plugin performs its 0.2.0 migration while active.

== Settings ==

Settings > Email Verification

Delete pending accounts after
    Default: 7 days. Enter 0 to disable automatic deletion. Valid range: 0 to 365 days.

Other outbound email
    Enabled by default. Suppresses normal wp_mail() messages to pending account addresses. This covers WordPress core and plugins that use wp_mail(), but cannot intercept a plugin that sends through its own external API or transport.

Cleanup status
    Shows the current pending-account count and the next scheduled cleanup. Includes a Run cleanup now button for testing or immediate maintenance.

== Suggested upgrade test ==

1. Keep an existing administrator session open.
2. Upload and replace the 0.1.0 plugin with 0.2.0.
3. Open Settings > Email Verification.
4. Confirm retention is 7 days and outbound suppression is enabled.
5. Confirm your already verified test account still shows Verified under Users.
6. Create a new test registration and confirm its verification message still arrives.
7. Before verification, trigger any available site notification to that user and verify that no message is sent.
8. Verify the account and repeat the notification; it should now be sent normally.
9. For cleanup testing, temporarily set retention to 1 day and use a deliberately old pending account, or adjust that test account's user_registered value in a staging copy. Click Run cleanup now.

On nidhoggur, the existing mail log can be watched with:

    sudo tail -f /var/log/maillog

If WP-Cron is disabled in wp-config.php, invoke wp-cron.php from the server's real cron or use WP-CLI so daily cleanup runs reliably.

== Security and safety notes ==

* Tokens contain 256 bits of randomness.
* Only an HMAC-SHA256 token hash is stored in user metadata.
* Links expire after 48 hours by default.
* A newly requested link replaces the previous link.
* Public resend requests are limited to one accepted request every five minutes per pending account.
* Public resend responses do not disclose whether an account exists.
* Existing administrators are exempt from login blocking and cleanup to prevent accidental lockout.
* Accounts that own WordPress posts are skipped during automated cleanup.
* Mail suppression returns a successful handled result for pending-only messages so notification plugins do not repeatedly retry an intentionally discarded message.
* Accounts without this plugin's explicit Pending marker are treated as verified, preventing accidental lockouts after an interrupted upgrade or temporary plugin deactivation.

== Developer filters/actions ==

wrav_ev_link_lifetime
    Verification lifetime in seconds. Default: 48 hours. Minimum: 1 hour.

wrav_ev_resend_cooldown
    Public resend cooldown in seconds. Default: 5 minutes. Minimum: 1 minute.

wrav_ev_cleanup_batch_size
    Maximum pending accounts examined per daily cleanup. Default: 500. Range enforced: 1 to 5000.

wrav_ev_auto_verify_new_user
    Boolean controlling whether a newly created user should be automatically verified.

wrav_ev_email_subject
    Verification email subject.

wrav_ev_email_message
    Plain-text verification email message.

wrav_ev_after_verification_url
    Local redirect URL after successful verification.

wrav_ev_should_delete_pending_user
    Boolean controlling deletion of an individual expired pending account.

wrav_ev_user_verified
    Action fired after an account is verified.

wrav_ev_pending_user_deleted
    Action fired after an expired pending account is deleted.

wrav_ev_pending_user_cleanup_skipped
    Action fired when cleanup skips a pending account.

wrav_ev_mail_suppressed
    Action fired when a pending-only wp_mail() message is intentionally suppressed.

== Changelog ==

= 0.2.0 =
* Added configurable automatic deletion of stale pending accounts; default 7 days.
* Added a daily WP-Cron cleanup event and a manual cleanup button.
* Added a Settings > Email Verification page with pending count and schedule status.
* Added generic suppression of wp_mail() messages to pending account addresses.
* Added mixed-recipient filtering so verified and outside recipients still receive mail.
* Added safety exclusions for administrators and pending accounts that own WordPress content.
* Treat accounts without an explicit pending marker as verified to avoid accidental lockouts.
* Added active-plugin upgrade handling without requiring deactivation/reactivation.

= 0.1.0 =
* Initial test release.
