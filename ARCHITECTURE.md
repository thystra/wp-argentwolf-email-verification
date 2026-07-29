# ArgentWolf Email Verification Architecture

## Purpose

ArgentWolf Email Verification keeps newly self-registered WordPress accounts
inactive until the account owner confirms control of the registered email
address. Verification is handled locally by WordPress and `wp_mail()` without an
external verification API.

The plugin is also the verification authority for compatible plugins such as
ArgentWolf Post Notifier. Integrations must use the public API rather than
inspecting private user metadata or relying on mail-send return values.

## Component boundaries

The plugin owns:

- verification state for registered WordPress users;
- verification-token creation and validation;
- blocking login and Application Password use for pending accounts;
- verification and resend user interfaces;
- administrator status, verify, resend, retention, and cleanup controls;
- optional suppression of ordinary `wp_mail()` messages to pending users;
- a stable public verification API and verification lifecycle actions;
- privacy-policy guidance and personal-data integration.

The plugin does not:

- validate mailbox existence through an external API;
- manage newsletter subscribers who are not WordPress users;
- replace SMTP, bounce processing, or delivery monitoring;
- establish that a message reached an inbox merely because `wp_mail()` returned
  success;
- own campaigns or post-notification preferences.

## Runtime entry point

The canonical main file is:

```text
argentwolf-email-verification.php
```

It registers hooks from the `ArgentWolf_Email_Verification` class and exposes
small procedural integration functions after the class is loaded.

The public product, slug, main file, text domain, API functions, and new public
actions use the `argentwolf` name. Existing `wrav_ev_*` database keys and selected
legacy hooks are retained as compatibility identifiers until a dedicated data
migration is tested.

## User states

A user has one of three API-visible states:

- `verified`: the explicit metadata value is verified, or no pending marker is
  present;
- `pending`: the explicit metadata value is pending;
- `unknown`: the requested WordPress user does not exist.

Missing metadata intentionally behaves as verified for an existing account.
This fail-open rule prevents a temporary plugin interruption or partial upgrade
from locking out established users.

The global public API is:

```php
argentwolf_email_verification_is_user_verified( int $user_id ): bool
argentwolf_email_verification_get_user_verification_status( int $user_id ): string
```

The Boolean API returns `false` for unknown users. The status API returns
`verified`, `pending`, or `unknown`.

## Registration lifecycle

1. WordPress creates a user.
2. The `user_register` handler determines whether the account is trusted:
   - WP-CLI-created accounts are verified;
   - administrators and accounts deliberately created by a logged-in user with
     `create_users` are verified;
   - other registrations become pending.
3. A pending account receives a fresh one-time verification token.
4. The normal WordPress new-user email is suppressed while pending.
5. Password and Application Password authentication are blocked while pending.
6. A valid, unexpired token changes the account to verified.
7. A native WordPress registrant is directed to set a password; other
   registration paths return to login.

The plugin must continue returning a generic response for public resend requests
so an attacker cannot enumerate users.

## Token design

Tokens contain 256 bits of randomness generated with `random_bytes()`. The
message contains the raw token, while user metadata stores only an HMAC-SHA256
hash keyed with a WordPress salt.

A newly issued token replaces the previous token. Tokens expire after a
filterable lifetime, currently 48 hours by default. Public resend requests are
accepted no more frequently than the configured cooldown.

A future security milestone should consider a scanner-resistant confirmation
page that requires a deliberate POST before activating an account. Until that
change is implemented, token links must remain single-use and short-lived.

## Mail suppression

When enabled, the plugin inspects recipients passed through `wp_mail()`:

- a pending address is removed from mixed-recipient messages;
- a message containing only recognized pending addresses is intentionally
  preempted;
- the plugin’s own verification message bypasses suppression;
- unknown outside addresses are not classified as pending.

The preempted path returns a handled result to stop calling plugins from
repeatedly retrying intentionally suppressed messages. Consequently,
`wp_mail() === true` is not evidence that a pending user received a message.
Companion plugins must call the verification API before queueing or sending.

## Cleanup

A daily WP-Cron event examines expired pending accounts in bounded batches.
Retention defaults to seven days and may be disabled.

Cleanup never deletes:

- administrators;
- accounts that own WordPress posts;
- accounts vetoed by the cleanup filter.

A manual cleanup action is available to administrators and uses WordPress nonce
and capability checks.

If WP-Cron is disabled, the site operator must invoke WordPress cron through a
real scheduler or WP-CLI.

## Settings and support

The settings page is under **Settings → Email Verification** and contains:

- pending-account retention;
- pending-recipient mail suppression;
- pending-account count;
- next scheduled cleanup;
- a manual cleanup action;
- a public GitHub project link for issues, contributions, and support of further
  development.

The support link is informational and must not load remote scripts, tracking
pixels, advertisements, or executable content in WordPress administration.

## Privacy

The plugin stores verification state, token hashes, expiration timestamps,
message timestamps, and registration-workflow state in user metadata.

Privacy integration must:

- provide suggested site privacy-policy text;
- export understandable verification status and relevant timestamps;
- never export token hashes;
- erase expendable token and message metadata when appropriate;
- retain the minimum status needed to prevent a pending account from becoming
  active through erasure;
- explain retained security state in eraser results.

Deleting a WordPress user removes the associated user metadata through WordPress
core.

## Source migration and applicator policy

Versioned applicators build the complete candidate repository in a temporary
staging tree before creating a backup or changing the checkout. PHP migration
logic is scoped to named methods and class structure; global occurrence-count
anchors are not an accepted maintenance mechanism. The staged main file must
pass PHP syntax checks and repository validation before it can replace the
tracked baseline.

After staged validation succeeds, the applicator creates a rollback archive
under `~/src/backups/wp-argentwolf-email-verification-backups/`, replaces the
worktree files as a complete set, and validates the installed result. Any
post-backup failure restores the pre-change archive automatically.

## Compatibility and upgrade strategy

The 0.3.0 naming transition changes the public name, main file, class, text
domain, settings slug, documentation, and API. Existing option and metadata keys
remain unchanged so verified and pending accounts preserve their state.

Renaming the main file changes the plugin basename. A deployment upgrading from
the former main filename must be treated as a controlled plugin replacement:
keep an administrator session open, take a backup, install the canonical build,
activate it, and verify existing account states before removing the old plugin
copy.

No automated database-key migration should be introduced until it has
transactional, interruption, multisite, rollback, and large-user-table tests.

## WordPress.org packaging

The Git repository contains contributor documentation and build tooling. The
public plugin ZIP should contain only runtime and directory-facing files:

```text
argentwolf-email-verification/
├── argentwolf-email-verification.php
├── LICENSE
└── readme.txt
```

The release build must:

- place files under the `argentwolf-email-verification` directory;
- synchronize the PHP header version and `readme.txt` Stable Tag;
- exclude Git metadata, development tools, backups, tests, and private notes;
- pass PHP syntax validation and repository validation;
- pass WordPress Plugin Check before submission;
- be installed and tested as a ZIP on a clean staging site.

WordPress.org approval remains a manual review decision. Automated checks and
this architecture document do not establish approval.
