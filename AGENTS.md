# AGENTS.md

## Project identity

- Public name: **ArgentWolf Email Verification**
- WordPress.org slug: `argentwolf-email-verification`
- Git repository: `https://github.com/thystra/wp-argentwolf-email-verification`
- Normal local checkout: `~/src/wp-argentwolf-email-verification`
- Backup root: `~/src/backups/wp-argentwolf-email-verification-backups`
- Main plugin file: `argentwolf-email-verification.php`
- Text domain: `argentwolf-email-verification`
- License: GPL-2.0-or-later

Do not shorten the public product name to “Argent Email Verification” or restore
the former Wolf & Raven product name.

## Working conventions

Before giving an operator command, identify the computer or environment where it
runs. Use complete, copy-pasteable commands and full paths when a path is not
obvious from the current directory.

Protect existing work:

- Require a clean worktree before applying generated patches unless the patch
  explicitly supports local changes.
- Put backups below
  `~/src/backups/wp-argentwolf-email-verification-backups/`, never beside the
  repository at the top of `~/src`.
- Do not edit PHP source through global text-replacement or occurrence-count
  anchors. Build and validate complete candidate files in a temporary tree, then
  install them only after the candidate passes syntax and project checks.
- When a structural source migration is unavoidable, scope edits by named
  functions or classes and validate the complete generated file before touching
  the checkout.
- Do not confuse temporary tool paths such as `/mnt/data/...` with paths on a
  developer or production computer.
- Do not claim that a patch, build, deployment, or submission succeeded without
  corresponding command output.

When practical, use versioned applicator scripts. Applicators must validate the
repository, build and validate their complete staged result before repository
changes, preserve a rollback copy, fail safely, and run focused validation after
installation.

## Public-repository privacy

Public files must not contain:

- personal names that are not intentionally part of authorship or contribution
  metadata;
- private hostnames, LAN addresses, production paths, mail-log paths, or other
  infrastructure details;
- passwords, API tokens, private keys, certificate contents, or secrets;
- assumptions tied to one administrator’s operating environment.

Use portable paths such as `~/src/...` in contributor documentation. Put private
deployment notes outside the public repository.

## Architecture invariants

- Existing users are treated as verified on first activation.
- New self-registered users are pending until email verification succeeds.
- Administrators must not be locked out by plugin state.
- Unknown or missing verification metadata remains fail-open for existing
  accounts to avoid accidental lockout after an interrupted upgrade.
- Verification tokens use cryptographically secure randomness; only a keyed hash
  is stored.
- Public resend responses do not reveal whether an account exists.
- Pending-recipient suppression must never block the plugin’s own verification
  message.
- The public verification API is the supported integration point for companion
  plugins. Do not infer verification from `wp_mail()` return values.
- Legacy `wrav_ev_*` option and metadata keys are retained until a separately
  tested migration is approved. Their presence does not authorize old
  user-facing branding.
- Security-sensitive state must not be silently removed during uninstall or
  privacy erasure when doing so would activate a pending account.

## WordPress engineering requirements

Follow WordPress coding, security, privacy, accessibility, and internationalization
practices:

- validate and sanitize input;
- check capabilities and nonces for privileged actions;
- escape output as late as practical;
- use prepared SQL;
- keep public responses resistant to user enumeration;
- use WordPress APIs instead of external services where possible;
- add privacy-policy text and privacy exporter/eraser support for stored personal
  data;
- do not include a custom update checker in the WordPress.org build;
- keep version headers and the `readme.txt` Stable Tag synchronized.

The plugin must remain usable without an external email-verification API. Email is
sent through `wp_mail()` and the site’s configured mail transport.

## Required validation

Before committing a release candidate:

```bash
cd ~/src/wp-argentwolf-email-verification

bash scripts/validate.sh
composer validate --strict
composer test
git status --short --branch
git diff --check
git diff
```

Before a WordPress.org submission, also run Plugin Check against the built ZIP in
a staging WordPress installation and exercise runtime checks.

Review and release commands should include, as appropriate:

```bash
cd ~/src/wp-argentwolf-email-verification

git add --all
git commit -m "Describe the change"
git push origin main
git tag -a vX.Y.Z -m "ArgentWolf Email Verification X.Y.Z"
git push origin vX.Y.Z
```

Do not tag until the release ZIP, changelog, Stable Tag, plugin header version,
and staging behavior all agree.
