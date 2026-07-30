# ArgentWolf Email Verification

ArgentWolf Email Verification keeps newly self-registered WordPress accounts
inactive until the account owner confirms control of the registered email
address. Verification is handled locally through WordPress and `wp_mail()`.

The plugin is also the authoritative registered-user verification provider for
compatible plugins such as ArgentWolf Post Notifier.

## Public integration API

```php
argentwolf_email_verification_is_user_verified( int $user_id ): bool
argentwolf_email_verification_get_user_verification_status( int $user_id ): string
```

The status function returns `verified`, `pending`, or `unknown`. Missing legacy
pending metadata remains verified for an existing user so an interrupted
upgrade cannot lock out established accounts.

New integrations must use canonical `argentwolf_email_verification_` functions
and hooks. See `docs/legacy-api-deprecation.md` for the compatibility policy.

## Development

Normal checkout:

```text
~/src/wp-argentwolf-email-verification
```

Install dependencies and run the source-level suite:

```bash
composer install
composer validate --strict
composer test
```

Install the WordPress 7.0.2 test environment and run integration tests:

```bash
bash bin/install-wp-tests.sh \
  wordpress_test \
  root \
  '' \
  127.0.0.1:3306 \
  7.0.2

composer test:integration
```

Build the reviewed runtime package:

```bash
bash scripts/build-release.sh
```

The release package intentionally contains only the main plugin file,
`readme.txt`, and `LICENSE`.

<!-- EOF: README.md -->
