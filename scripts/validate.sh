#!/usr/bin/env bash
# ~/src/wp-argentwolf-email-verification/scripts/validate.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN="$ROOT/argentwolf-email-verification.php"
README="$ROOT/readme.txt"

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ -f "$MAIN" ]] || fail "Missing canonical main plugin file: $MAIN"
[[ -f "$README" ]] || fail "Missing WordPress.org readme: $README"

php -l "$MAIN"

plugin_version="$(
    sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$MAIN" |
        head -n 1 |
        tr -d '\r'
)"
stable_tag="$(
    sed -n 's/^Stable tag:[[:space:]]*//p' "$README" |
        head -n 1 |
        tr -d '\r'
)"

[[ -n "$plugin_version" ]] || fail "Could not read Version from plugin header."
[[ -n "$stable_tag" ]] || fail "Could not read Stable tag from readme.txt."
[[ "$plugin_version" == "$stable_tag" ]] ||
    fail "Plugin version $plugin_version does not match Stable tag $stable_tag."

grep -q '^=== ArgentWolf Email Verification ===$' "$README" ||
    fail "readme.txt title is not canonical."
grep -q 'Text Domain: argentwolf-email-verification' "$MAIN" ||
    fail "Plugin text domain is not canonical."
grep -q 'Plugin Name: ArgentWolf Email Verification' "$MAIN" ||
    fail "Plugin display name is not canonical."

public_files=(
    "$ROOT/AGENTS.md"
    "$ROOT/ARCHITECTURE.md"
    "$ROOT/TODO.md"
    "$ROOT/README.md"
    "$ROOT/readme.txt"
    "$ROOT/argentwolf-email-verification.php"
)

for file in "${public_files[@]}"; do
    [[ -f "$file" ]] || fail "Expected public file is missing: $file"
done

if grep -nE '/home/[[:alnum:]_.-]+/|192\.168\.|10\.[0-9]+\.[0-9]+\.[0-9]+' "${public_files[@]}"; then
    fail "Public files contain a machine-specific home path or private IPv4 address."
fi

if grep -nE 'Wolf[[:space:]]*&[[:space:]]*Raven Local Email Verification|Text Domain:[[:space:]]*wolf-raven-email-verification' "${public_files[@]}"; then
    fail "Public branding or text domain still uses the former name."
fi

readme_bytes="$(wc -c < "$README")"
if (( readme_bytes > 10240 )); then
    fail "readme.txt is larger than 10 KiB ($readme_bytes bytes)."
fi

if command -v git >/dev/null 2>&1 && git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git -C "$ROOT" diff --check
fi

printf 'PASS: PHP syntax is valid.\n'
printf 'PASS: plugin version and Stable tag both equal %s.\n' "$plugin_version"
printf 'PASS: public naming and path checks passed.\n'
printf 'PASS: readme.txt size is %s bytes.\n' "$readme_bytes"
printf 'NOTE: WordPress Plugin Check and runtime staging tests are still required.\n'

# EOF: ~/src/wp-argentwolf-email-verification/scripts/validate.sh
