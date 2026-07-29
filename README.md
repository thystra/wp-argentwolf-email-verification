# ArgentWolf Email Verification

ArgentWolf Email Verification keeps newly self-registered WordPress accounts
inactive until the account owner verifies the registered email address.

Verification is processed locally with WordPress and `wp_mail()`. The plugin does
not call an external email-verification API or send registration data to a
third-party verification service.

## Features

- Marks new self-registered accounts pending.
- Sends a one-time verification link.
- Blocks password and Application Password login while pending.
- Suppresses the normal WordPress user email until verification.
- Provides public resend without revealing whether an account exists.
- Shows Pending or Verified status in the Users screen.
- Allows administrators to verify or resend.
- Deletes stale pending accounts on a configurable schedule while protecting
  administrators and content owners.
- Optionally suppresses ordinary `wp_mail()` messages to pending account
  addresses.
- Exposes a public API for compatible plugins.
- Includes WordPress privacy-policy, exporter, and eraser integration.

## Public integration API

```php
$is_verified = argentwolf_email_verification_is_user_verified( $user_id );
$status      = argentwolf_email_verification_get_user_verification_status( $user_id );
```

`$status` is `verified`, `pending`, or `unknown`.

Do not inspect the plugin’s private user metadata. Legacy storage identifiers are
retained for upgrade safety and are not the supported integration contract.

## Development

Normal checkout:

```bash
cd ~/src/wp-argentwolf-email-verification
```

Validate:

```bash
bash scripts/validate.sh
```

Build a directory-ready ZIP:

```bash
bash scripts/build-release.sh
```

Generated packages are placed in `dist/`.

Project issues and contributions:
`https://github.com/thystra/wp-argentwolf-email-verification`

## Documentation

- `ARCHITECTURE.md` — design, state model, security, privacy, and packaging.
- `TODO.md` — milestones and WordPress.org submission work.
- `AGENTS.md` — contributor and automation instructions.

## License

GPL-2.0-or-later.
