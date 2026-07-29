#!/usr/bin/env bash
# ~/src/wp-argentwolf-email-verification/scripts/build-release.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="argentwolf-email-verification"
MAIN="$ROOT/argentwolf-email-verification.php"
DIST="$ROOT/dist"

"$ROOT/scripts/validate.sh"

version="$(
    sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$MAIN" |
        head -n 1 |
        tr -d '\r'
)"

[[ -n "$version" ]] || {
    printf 'ERROR: Could not determine plugin version.\n' >&2
    exit 1
}

command -v zip >/dev/null 2>&1 || {
    printf 'ERROR: zip is required to build the release package.\n' >&2
    exit 1
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

package_root="$tmp/$SLUG"
mkdir -p "$package_root" "$DIST"

install -m 0644 "$ROOT/argentwolf-email-verification.php" "$package_root/"
install -m 0644 "$ROOT/readme.txt" "$package_root/"
install -m 0644 "$ROOT/LICENSE" "$package_root/"

archive="$DIST/${SLUG}-${version}.zip"
rm -f "$archive"

(
    cd "$tmp"
    zip -q -r "$archive" "$SLUG"
)

sha256sum "$archive" > "$archive.sha256"

printf 'Built: %s\n' "$archive"
printf 'Checksum: %s\n' "$archive.sha256"
printf 'Package manifest:\n'
unzip -Z1 "$archive"

# EOF: ~/src/wp-argentwolf-email-verification/scripts/build-release.sh
