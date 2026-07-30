# ArgentWolf Email Verification Legacy API Deprecation

<!-- File: docs/legacy-api-deprecation.md -->

## Supported integration surface

New integrations must use only canonical public functions and hooks whose names
begin with `argentwolf_email_verification_`.

The verification-provider contract is:

```php
argentwolf_email_verification_is_user_verified( int $user_id ): bool
argentwolf_email_verification_get_user_verification_status( int $user_id ): string
```

The canonical successful-verification action is:

```text
argentwolf_email_verification_user_verified
```

## Legacy compatibility

Legacy `wrav_ev_*` option names, user-metadata names, actions, filters, request
parameters, and nonce-action names remain compatibility identifiers. They are
not the supported naming surface for new plugins.

**No removal before 1.0.0.** The 0.x series will preserve legacy runtime
identifiers required by existing installations.

A removal or migration after 1.0.0 requires:

1. a separately documented deprecation release;
2. at least one stable release carrying runtime deprecation notices where those
   notices are safe and non-disruptive;
3. migration tests for verified, pending, missing-meta, administrator, deleted,
   and changed-email users;
4. documented upgrade and rollback procedures;
5. confirmation that ArgentWolf Post Notifier and other known integrations use
   only canonical APIs; and
6. an explicit changelog entry naming every removed identifier.

Private storage must never be treated as an external API. Companion plugins
must not inspect `_wrav_ev_verified` or other legacy metadata directly.

## Mail-result limitation

A successful `wp_mail()` return value does not establish verification or inbox
delivery. The plugin can intentionally preempt mail to pending accounts while
reporting the request as handled. Integrations must call the public verification
API before queueing and immediately before sending registered-user mail.

<!-- EOF: docs/legacy-api-deprecation.md -->
