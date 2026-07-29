# ArgentWolf Email Verification TODO

## Milestone 0 — Preserve the working baseline

- [x] Preserve existing account-state behavior.
- [x] Retain existing `wrav_ev_*` option and user-metadata keys for upgrade
  safety.
- [x] Keep administrator lockout protections.
- [x] Keep pending-recipient suppression and cleanup behavior.
- [ ] Capture a production-neutral regression checklist from the existing
  deployment before replacing the main plugin file.

## Milestone 1 — Canonical ArgentWolf naming

- [x] Rename the public plugin to **ArgentWolf Email Verification**.
- [x] Use `argentwolf-email-verification` as the WordPress.org slug and text
  domain.
- [x] Rename the main file to `argentwolf-email-verification.php`.
- [x] Rename the main class to `ArgentWolf_Email_Verification`.
- [x] Remove private host and filesystem references from public documentation.
- [x] Add `AGENTS.md`, `ARCHITECTURE.md`, `TODO.md`, `LICENSE`, and a useful
  repository `README.md`.
- [ ] Verify the intended WordPress.org contributor username before submission.
- [ ] Run the WordPress Plugin Check naming tool and confirm no confusingly
  similar directory name exists.

## Milestone 2 — Integration API

- [x] Add `argentwolf_email_verification_is_user_verified()`.
- [x] Add `argentwolf_email_verification_get_user_verification_status()`.
- [x] Add canonical verification, cleanup, and mail-suppression actions while
  retaining legacy aliases.
- [ ] Add PHPUnit coverage for valid, pending, verified, and unknown user API
  results.
- [ ] Document a versioned deprecation plan for legacy public hooks.
- [ ] Integrate ArgentWolf Post Notifier exclusively through the public API.

## Milestone 3 — Settings and project support

- [x] Add the public GitHub repository link to the settings page.
- [x] Add a GitHub link to the plugin action row.
- [x] Keep the link passive: no remote admin assets, telemetry, advertisements,
  or update checks.
- [ ] Decide whether a separate contribution/donation URL belongs in a future
  release; do not make it required or intrusive.

## Milestone 4 — Privacy

- [x] Add suggested privacy-policy text.
- [x] Add personal-data exporter integration.
- [x] Exclude token hashes from exports.
- [x] Add eraser handling that removes expendable token/message metadata while
  retaining security-critical verification state.
- [ ] Add automated exporter and eraser tests.
- [ ] Review wording with the final behavior before directory submission.

## Milestone 5 — Verification security hardening

- [ ] Replace immediate GET activation with a scanner-resistant confirmation
  screen and deliberate POST.
- [ ] Preserve compatibility for already issued legacy verification links
  during the transition window.
- [ ] Add token replay, expiration, replacement, malformed-token, and concurrent
  verification tests.
- [ ] Add rate-limit tests for public resend requests.
- [ ] Evaluate an additional site-wide or IP-aware abuse-control filter without
  storing unnecessary personal data.
- [ ] Verify all public responses remain resistant to user enumeration.

## Milestone 6 — Automated testing and coding standards

- [ ] Introduce PHPUnit with the WordPress test suite.
- [ ] Test first activation with existing users.
- [ ] Test administrator-created, WP-CLI-created, native self-registered, and
  third-party-form-created users.
- [ ] Test password and Application Password blocking.
- [ ] Test verification delivery bypasses pending-recipient suppression.
- [ ] Test pending-only, mixed, header-based CC/BCC, and outside-recipient mail.
- [ ] Test cleanup exclusions, batching, retention disabled, and manual cleanup.
- [ ] Add WordPress Coding Standards and PHPCompatibility checks.
- [ ] Resolve all required Plugin Check findings.
- [ ] Run tests across supported PHP and WordPress versions.

## Milestone 7 — Upgrade and basename transition

- [ ] Build a staging upgrade procedure from
  `wolf-raven-email-verification.php` to
  `argentwolf-email-verification.php`.
- [ ] Verify existing verified and pending accounts retain state.
- [ ] Verify old outstanding verification URLs remain usable or are replaced
  with a documented resend requirement.
- [ ] Verify no duplicate plugin copy remains after the controlled replacement.
- [ ] Add a rollback test using the repository backup.
- [ ] Decide whether legacy storage keys remain indefinitely or receive a later,
  separately tested migration.

## Milestone 8 — Release tooling

- [x] Replace in-place PHP string-rewrite applicators with a staged,
  structure-aware complete-file migration.
- [x] Validate the complete staged repository before creating a backup or
  changing the checkout.
- [x] Add transactional rollback for any failure after installation begins.
- [x] Add repository validation tooling.
- [x] Add a deterministic release ZIP builder.
- [x] Keep generated packages under ignored `dist/`.
- [x] Add checksums to release output.
- [ ] Add a GitHub Actions quality workflow after local standards checks pass.
- [ ] Add reproducible release notes and an annotated tag procedure.
- [ ] Confirm the built ZIP contains no contributor-only or personalized files.

## Milestone 9 — WordPress.org submission readiness

- [ ] Freeze the initial directory display name and requested slug.
- [ ] Confirm the plugin header includes correct version, license, WordPress,
  PHP, URI, author, and text-domain values.
- [ ] Confirm `readme.txt` validates and its Stable Tag matches the plugin
  version.
- [ ] Confirm all code and bundled assets are GPL-compatible.
- [ ] Run static and runtime WordPress Plugin Check against the built ZIP.
- [ ] Test with `WP_DEBUG` enabled and inspect PHP and mail logs for warnings.
- [ ] Install, activate, configure, verify, resend, clean up, deactivate, and
  reactivate on a clean staging site.
- [ ] Test multisite behavior or explicitly document that multisite has not yet
  been validated.
- [ ] Prepare WordPress.org icon, banner, and screenshots separately from the
  runtime ZIP.
- [ ] Submit a complete, production-ready ZIP for review.
- [ ] After approval, establish the WordPress.org SVN release workflow.
- [ ] Do not submit ArgentWolf Post Notifier with this plugin as a required
  directory dependency until this plugin’s WordPress.org slug is approved.

## Deferred ideas

- Configurable verification-email subject and message in the settings UI.
- Optional HTML verification email with a plain-text alternative.
- WP-CLI commands for status, resend, verify, and cleanup.
- Multisite network settings and network-user semantics.
- Optional retention reporting without collecting unnecessary analytics.
