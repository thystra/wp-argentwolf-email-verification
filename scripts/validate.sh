#!/usr/bin/env bash
# File: scripts/validate.sh
#
# Validate the source tree before packaging or release review.

main() {
    local root
    local main_file
    local readme
    local plugin_version
    local stable_tag
    local readme_bytes
    local file
    local public_files

    root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." 2>/dev/null && pwd)"
    main_file="${root}/argentwolf-email-verification.php"
    readme="${root}/readme.txt"

    fail() {
        printf 'ERROR: %s\n' "$*" >&2
        return 1
    }

    [[ -f "${main_file}" ]] || {
        fail "Missing canonical main plugin file: ${main_file}"
        return 1
    }
    [[ -f "${readme}" ]] || {
        fail "Missing WordPress.org readme: ${readme}"
        return 1
    }

    while IFS= read -r -d '' file; do
        php -l "${file}" || return 1
    done < <(
        find "${root}" \
            -path "${root}/vendor" -prune -o \
            -path "${root}/dist" -prune -o \
            -type f -name '*.php' -print0
    )

    plugin_version="$(
        sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' \
            "${main_file}" |
            head -n 1 |
            tr -d '\r'
    )"
    stable_tag="$(
        sed -n 's/^Stable tag:[[:space:]]*//p' "${readme}" |
            head -n 1 |
            tr -d '\r'
    )"

    [[ -n "${plugin_version}" ]] || {
        fail 'Could not read Version from plugin header.'
        return 1
    }
    [[ -n "${stable_tag}" ]] || {
        fail 'Could not read Stable tag from readme.txt.'
        return 1
    }
    [[ "${plugin_version}" == "${stable_tag}" ]] || {
        fail "Plugin version ${plugin_version} does not match Stable tag ${stable_tag}."
        return 1
    }

    grep -q '^=== ArgentWolf Email Verification ===$' "${readme}" || {
        fail 'readme.txt title is not canonical.'
        return 1
    }
    grep -q 'Text Domain: argentwolf-email-verification' "${main_file}" || {
        fail 'Plugin text domain is not canonical.'
        return 1
    }
    grep -q 'Plugin Name: ArgentWolf Email Verification' "${main_file}" || {
        fail 'Plugin display name is not canonical.'
        return 1
    }

    public_files=(
        "${root}/AGENTS.md"
        "${root}/ARCHITECTURE.md"
        "${root}/TODO.md"
        "${root}/README.md"
        "${root}/readme.txt"
        "${root}/argentwolf-email-verification.php"
        "${root}/docs/legacy-api-deprecation.md"
    )

    for file in "${public_files[@]}"; do
        [[ -f "${file}" ]] || {
            fail "Expected public file is missing: ${file}"
            return 1
        }
    done

    if grep -nE \
        '/home/[[:alnum:]_.-]+/|192\.168\.|10\.[0-9]+\.[0-9]+\.[0-9]+' \
        "${public_files[@]}"; then
        fail 'Public files contain a machine-specific path or private IPv4 address.'
        return 1
    fi

    if grep -nE \
        'Wolf[[:space:]]*&[[:space:]]*Raven Local Email Verification|Text Domain:[[:space:]]*wolf-raven-email-verification' \
        "${public_files[@]}"; then
        fail 'Public branding or text domain still uses the former name.'
        return 1
    fi

    readme_bytes="$(wc -c < "${readme}")"
    if (( readme_bytes > 10240 )); then
        fail "readme.txt is larger than 10 KiB (${readme_bytes} bytes)."
        return 1
    fi

    if command -v composer >/dev/null 2>&1; then
        (
            cd "${root}" &&
            COMPOSER_ROOT_VERSION='0.3.4' composer validate --strict
        ) || return 1

        if [[ -r "${root}/vendor/autoload.php" ]]; then
            (
                cd "${root}" &&
                COMPOSER_ROOT_VERSION='0.3.4' composer test
            ) || return 1
        fi
    fi

    if command -v git >/dev/null 2>&1 &&
        git -C "${root}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git -C "${root}" diff --check || return 1
    fi

    printf 'PASS: PHP syntax is valid.\n'
    printf 'PASS: plugin version and Stable Tag both equal %s.\n' \
        "${plugin_version}"
    printf 'PASS: public naming and path checks passed.\n'
    printf 'PASS: readme.txt size is %s bytes.\n' "${readme_bytes}"
    printf 'NOTE: Plugin Check and runtime staging tests remain separate gates.\n'
    return 0
}

main "$@"

# EOF: scripts/validate.sh
