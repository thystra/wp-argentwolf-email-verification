#!/usr/bin/env python3
"""Build the canonical ArgentWolf Email Verification 0.3.0 main file.

This script reads the reviewed 0.2.0 main file, performs method-scoped and
structure-aware changes in memory, validates the result, and writes a complete
new main file. It never edits the source file in place.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path
from typing import Callable

OLD_CLASS = "WRAV_Local_Email_Verification"
NEW_CLASS = "ArgentWolf_Email_Verification"
OLD_TEXT_DOMAIN = "wolf-raven-email-verification"
NEW_TEXT_DOMAIN = "argentwolf-email-verification"


def fail(message: str) -> "NoReturn":
    raise SystemExit(f"ERROR: {message}")


def find_matching_brace(text: str, opening: int) -> int:
    if opening < 0 or opening >= len(text) or text[opening] != "{":
        fail("invalid opening brace supplied to structural scanner")

    depth = 0
    state = "normal"
    escaped = False
    i = opening

    while i < len(text):
        char = text[i]
        nxt = text[i + 1] if i + 1 < len(text) else ""

        if state == "single":
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == "'":
                state = "normal"
        elif state == "double":
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == '"':
                state = "normal"
        elif state == "line_comment":
            if char == "\n":
                state = "normal"
        elif state == "block_comment":
            if char == "*" and nxt == "/":
                state = "normal"
                i += 1
        else:
            if char == "'":
                state = "single"
            elif char == '"':
                state = "double"
            elif char == "/" and nxt == "/":
                state = "line_comment"
                i += 1
            elif char == "#":
                state = "line_comment"
            elif char == "/" and nxt == "*":
                state = "block_comment"
                i += 1
            elif char == "{":
                depth += 1
            elif char == "}":
                depth -= 1
                if depth == 0:
                    return i
                if depth < 0:
                    fail("brace scanner encountered an unexpected closing brace")
        i += 1

    fail("could not find matching closing brace")


def method_span(text: str, name: str) -> tuple[int, int]:
    pattern = re.compile(
        rf"(?m)^[\t ]*(?:public|private|protected)\s+static\s+function\s+{re.escape(name)}\s*\("
    )
    matches = list(pattern.finditer(text))
    if len(matches) != 1:
        fail(f"expected one method named {name}, found {len(matches)}")

    start = matches[0].start()
    opening = text.find("{", matches[0].end())
    if opening == -1:
        fail(f"method {name} has no opening brace")
    end = find_matching_brace(text, opening) + 1
    return start, end


def replace_method(text: str, name: str, replacement: str) -> str:
    start, end = method_span(text, name)
    return text[:start] + replacement.rstrip() + text[end:]


def mutate_method(text: str, name: str, mutator: Callable[[str], str]) -> str:
    start, end = method_span(text, name)
    original = text[start:end]
    changed = mutator(original)
    if changed == original:
        fail(f"method {name} was not changed as expected")
    return text[:start] + changed + text[end:]


def insert_before_method(text: str, name: str, addition: str) -> str:
    start, _ = method_span(text, name)
    return text[:start] + addition.rstrip() + "\n\n" + text[start:]


def insert_after_method(text: str, name: str, addition: str) -> str:
    _, end = method_span(text, name)
    return text[:end] + "\n\n" + addition.rstrip() + text[end:]


def replace_required(text: str, old: str, new: str, label: str, expected: int = 1) -> str:
    count = text.count(old)
    if count != expected:
        fail(f"expected {expected} {label} occurrence(s), found {count}")
    return text.replace(old, new, expected)


def build(source: str) -> str:
    if "Plugin Name: Wolf & Raven Local Email Verification" not in source:
        fail("source does not contain the reviewed 0.2.0 plugin header")
    if "Version: 0.2.0" not in source:
        fail("source does not contain the reviewed 0.2.0 version")
    if NEW_CLASS in source or "Plugin Name: ArgentWolf Email Verification" in source:
        fail("source already appears to contain the canonical 0.3.0 plugin")

    header_pattern = re.compile(r"\A<\?php\n/\*\*.*?\n \*/", re.S)
    header = """<?php
/**
 * Plugin Name: ArgentWolf Email Verification
 * Plugin URI: https://github.com/thystra/wp-argentwolf-email-verification
 * Description: Keeps newly self-registered accounts inactive until the user verifies the registered email address. Verification is processed locally through WordPress and wp_mail().
 * Version: 0.3.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Author: ArgentWolf
 * Author URI: https://github.com/thystra
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: argentwolf-email-verification
 */"""
    text, count = header_pattern.subn(header, source, count=1)
    if count != 1:
        fail(f"expected one plugin header, found {count}")

    text = text.replace(OLD_CLASS, NEW_CLASS)
    text = text.replace(f"'{OLD_TEXT_DOMAIN}'", f"'{NEW_TEXT_DOMAIN}'")
    text = text.replace("Wolf & Raven Email Verification", "ArgentWolf Email Verification")

    text, count = re.subn(
        r"(?m)^\tprivate const VERSION\s*=\s*'0\.2\.0';$",
        "\tprivate const VERSION            = '0.3.0';",
        text,
        count=1,
    )
    if count != 1:
        fail("could not update the VERSION constant")

    text, count = re.subn(
        r"(?m)^\tprivate const SETTINGS_PAGE\s*=\s*'wrav-email-verification';$",
        "\tprivate const SETTINGS_PAGE = 'argentwolf-email-verification';\n"
        "\tprivate const PROJECT_URL   = 'https://github.com/thystra/wp-argentwolf-email-verification';",
        text,
        count=1,
    )
    if count != 1:
        fail("could not update the settings-page constant")

    init_method = """\tpublic static function init(): void {
\t\tadd_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );

\t\tadd_action( 'user_register', array( __CLASS__, 'handle_new_user' ), 10, 2 );
\t\tadd_filter( 'wp_send_new_user_notification_to_user', array( __CLASS__, 'suppress_core_user_email' ), 10, 2 );
\t\tadd_filter( 'authenticate', array( __CLASS__, 'block_unverified_login' ), 100, 3 );
\t\tadd_filter( 'wp_is_application_passwords_available_for_user', array( __CLASS__, 'block_application_passwords' ), 10, 2 );

\t\tadd_filter( 'pre_wp_mail', array( __CLASS__, 'preempt_pending_only_mail' ), 999, 2 );
\t\tadd_filter( 'wp_mail', array( __CLASS__, 'filter_pending_recipients' ), 999 );
\t\tadd_action( 'init', array( __CLASS__, 'handle_verification_link' ), 1 );
\t\tadd_filter( 'login_message', array( __CLASS__, 'filter_login_message' ) );
\t\tadd_action( 'login_form', array( __CLASS__, 'add_resend_link_to_login' ) );

\t\tadd_action( 'admin_post_nopriv_wrav_ev_resend', array( __CLASS__, 'handle_public_resend' ) );
\t\tadd_action( 'admin_post_wrav_ev_resend', array( __CLASS__, 'handle_public_resend' ) );
\t\tadd_filter( 'manage_users_columns', array( __CLASS__, 'add_users_column' ) );
\t\tadd_filter( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );
\t\tadd_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_actions' ), 10, 2 );
\t\tadd_action( 'admin_post_wrav_ev_admin_verify', array( __CLASS__, 'handle_admin_verify' ) );
\t\tadd_action( 'admin_post_wrav_ev_admin_resend', array( __CLASS__, 'handle_admin_resend' ) );
\t\tadd_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
\t\tadd_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_cleanup' ) );
\t\tadd_action( 'admin_post_wrav_ev_run_cleanup', array( __CLASS__, 'handle_manual_cleanup' ) );

\t\tadd_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
\t\tadd_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
\t\tadd_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
\t\tadd_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
\t\tadd_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_personal_data_exporter' ) );
\t\tadd_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_personal_data_eraser' ) );
\t}"""
    text = replace_method(text, "init", init_method)

    def add_auto_verify_filter(method: str) -> str:
        legacy = "\t\t$auto_verify = (bool) apply_filters( 'wrav_ev_auto_verify_new_user', $auto_verify, $user, $userdata );"
        canonical = "\t\t$auto_verify = (bool) apply_filters( 'argentwolf_email_verification_auto_verify_new_user', $auto_verify, $user, $userdata );\n" + legacy
        return replace_required(method, legacy, canonical, "legacy auto-verify filter")

    text = mutate_method(text, "handle_new_user", add_auto_verify_filter)

    link_lifetime = """\tprivate static function link_lifetime(): int {
\t\t$seconds = (int) apply_filters( 'argentwolf_email_verification_link_lifetime', 48 * HOUR_IN_SECONDS );
\t\t$seconds = (int) apply_filters( 'wrav_ev_link_lifetime', $seconds );
\t\treturn max( HOUR_IN_SECONDS, $seconds );
\t}"""
    text = replace_method(text, "link_lifetime", link_lifetime)

    resend_cooldown = """\tprivate static function resend_cooldown(): int {
\t\t$seconds = (int) apply_filters( 'argentwolf_email_verification_resend_cooldown', 5 * MINUTE_IN_SECONDS );
\t\t$seconds = (int) apply_filters( 'wrav_ev_resend_cooldown', $seconds );
\t\treturn max( MINUTE_IN_SECONDS, $seconds );
\t}"""
    text = replace_method(text, "resend_cooldown", resend_cooldown)

    public_status_methods = """\t/**
\t * Public integration API: determine whether an existing user is verified.
\t */
\tpublic static function is_user_verified( int $user_id ): bool {
\t\t$user = $user_id > 0 ? get_userdata( $user_id ) : false;
\t\treturn $user instanceof WP_User && self::is_verified( $user_id );
\t}

\t/**
\t * Public integration API: return verified, pending, or unknown.
\t */
\tpublic static function get_user_verification_status( int $user_id ): string {
\t\t$user = $user_id > 0 ? get_userdata( $user_id ) : false;
\t\tif ( ! ( $user instanceof WP_User ) ) {
\t\t\treturn 'unknown';
\t\t}

\t\treturn self::is_pending( $user_id ) ? 'pending' : 'verified';
\t}"""
    text = insert_after_method(text, "is_verified", public_status_methods)

    mark_verified = """\tprivate static function mark_verified( int $user_id ): void {
\t\t$was_pending = self::is_pending( $user_id );

\t\tupdate_user_meta( $user_id, self::META_VERIFIED, '1' );
\t\tdelete_user_meta( $user_id, self::META_TOKEN_HASH );
\t\tdelete_user_meta( $user_id, self::META_TOKEN_EXPIRES );
\t\tdelete_user_meta( $user_id, self::META_SENT_AT );

\t\t$user = get_userdata( $user_id );
\t\tif ( $user instanceof WP_User ) {
\t\t\tself::invalidate_pending_email_cache( $user );

\t\t\tif ( $was_pending ) {
\t\t\t\tdo_action( 'argentwolf_email_verification_user_verified', $user_id, $user );
\t\t\t\tdo_action( 'wrav_ev_user_verified', $user_id, $user );
\t\t\t}
\t\t}
\t}"""
    text = replace_method(text, "mark_verified", mark_verified)

    def remove_duplicate_verified_action(method: str) -> str:
        pattern = re.compile(
            r"(?m)^(?P<indent>[\t ]*)self::mark_verified\( \$user_id \);\n"
            r"(?P=indent)do_action\( 'wrav_ev_user_verified', \$user_id, \$user \);$"
        )
        changed, count = pattern.subn(
            lambda match: f"{match.group('indent')}self::mark_verified( $user_id );",
            method,
            count=1,
        )
        if count != 1:
            fail(f"expected one duplicate verification action sequence, found {count}")
        return changed

    text = mutate_method(text, "handle_verification_link", remove_duplicate_verified_action)

    def add_email_filters(method: str) -> str:
        subject = "\t\t$subject = (string) apply_filters( 'wrav_ev_email_subject', $subject, $user, $verification_url );"
        subject_new = "\t\t$subject = (string) apply_filters( 'argentwolf_email_verification_email_subject', $subject, $user, $verification_url );\n" + subject
        method = replace_required(method, subject, subject_new, "legacy subject filter")

        message = "\t\t$message = (string) apply_filters( 'wrav_ev_email_message', $message, $user, $verification_url, $expires );"
        message_new = "\t\t$message = (string) apply_filters( 'argentwolf_email_verification_email_message', $message, $user, $verification_url, $expires );\n" + message
        return replace_required(method, message, message_new, "legacy message filter")

    text = mutate_method(text, "send_verification_email", add_email_filters)

    def add_mail_suppressed_action(method: str) -> str:
        legacy = "\t\t\tdo_action( 'wrav_ev_mail_suppressed', $atts, $analysis['emails'] );"
        canonical = "\t\t\tdo_action( 'argentwolf_email_verification_mail_suppressed', $atts, $analysis['emails'] );\n" + legacy
        return replace_required(method, legacy, canonical, "legacy mail-suppressed action")

    text = mutate_method(text, "preempt_pending_only_mail", add_mail_suppressed_action)

    def add_redirect_filters(method: str) -> str:
        pattern = re.compile(
            r"(?m)^(?P<indent>[\t ]*)\$url = \(string\) apply_filters\( 'wrav_ev_after_verification_url', \$url, \$user, \$result \);$"
        )

        def repl(match: re.Match[str]) -> str:
            indent = match.group("indent")
            return (
                f"{indent}$url = (string) apply_filters( 'argentwolf_email_verification_after_verification_url', $url, $user, $result );\n"
                f"{match.group(0)}"
            )

        changed, count = pattern.subn(repl, method)
        if count != 2:
            fail(f"expected two legacy redirect filters inside redirect_after_verification, found {count}")
        return changed

    text = mutate_method(text, "redirect_after_verification", add_redirect_filters)

    def add_cleanup_hooks(method: str) -> str:
        legacy_batch = "\t\t$batch_size = (int) apply_filters( 'wrav_ev_cleanup_batch_size', 500 );"
        canonical_batch = "\t\t$batch_size = (int) apply_filters( 'argentwolf_email_verification_cleanup_batch_size', 500 );\n\t\t$batch_size = (int) apply_filters( 'wrav_ev_cleanup_batch_size', $batch_size );"
        method = replace_required(method, legacy_batch, canonical_batch, "legacy cleanup batch filter")

        replacements = {
            "\t\t\t\tdo_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'owns_content' );":
                "\t\t\t\tdo_action( 'argentwolf_email_verification_pending_user_cleanup_skipped', $user_id, $user, 'owns_content' );\n"
                "\t\t\t\tdo_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'owns_content' );",
            "\t\t\t$should_delete = (bool) apply_filters( 'wrav_ev_should_delete_pending_user', true, $user, $cutoff );":
                "\t\t\t$should_delete = (bool) apply_filters( 'argentwolf_email_verification_should_delete_pending_user', true, $user, $cutoff );\n"
                "\t\t\t$should_delete = (bool) apply_filters( 'wrav_ev_should_delete_pending_user', $should_delete, $user, $cutoff );",
            "\t\t\t\tdo_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'filtered' );":
                "\t\t\t\tdo_action( 'argentwolf_email_verification_pending_user_cleanup_skipped', $user_id, $user, 'filtered' );\n"
                "\t\t\t\tdo_action( 'wrav_ev_pending_user_cleanup_skipped', $user_id, $user, 'filtered' );",
            "\t\t\t\tdo_action( 'wrav_ev_pending_user_deleted', $user_id, $user );":
                "\t\t\t\tdo_action( 'argentwolf_email_verification_pending_user_deleted', $user_id, $user );\n"
                "\t\t\t\tdo_action( 'wrav_ev_pending_user_deleted', $user_id, $user );",
        }
        for old, new in replacements.items():
            method = replace_required(method, old, new, "legacy cleanup hook")
        return method

    text = mutate_method(text, "cleanup_expired_pending_users", add_cleanup_hooks)

    privacy_methods = r"""\tpublic static function add_privacy_policy_content(): void {
\t\tif ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
\t\t\treturn;
\t\t}

\t\t$content  = '<p>' . esc_html__( 'ArgentWolf Email Verification stores a verification status, a keyed hash of the current one-time token, token expiration, verification-message time, and limited registration-workflow state in WordPress user metadata.', 'argentwolf-email-verification' ) . '</p>';
\t\t$content .= '<p>' . esc_html__( 'Verification emails are sent through the site\'s configured wp_mail() transport. Token hashes are not exported. Privacy erasure removes expendable token and message metadata but retains verification status needed to prevent a pending account from becoming active through erasure.', 'argentwolf-email-verification' ) . '</p>';

\t\twp_add_privacy_policy_content(
\t\t\t__( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ),
\t\t\twp_kses_post( $content )
\t\t);
\t}

\tpublic static function register_personal_data_exporter( array $exporters ): array {
\t\t$exporters['argentwolf-email-verification'] = array(
\t\t\t'exporter_friendly_name' => __( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ),
\t\t\t'callback'               => array( __CLASS__, 'export_personal_data' ),
\t\t);

\t\treturn $exporters;
\t}

\tpublic static function export_personal_data( string $email_address, int $page = 1 ): array {
\t\tunset( $page );

\t\t$user = get_user_by( 'email', $email_address );
\t\tif ( ! ( $user instanceof WP_User ) ) {
\t\t\treturn array(
\t\t\t\t'data' => array(),
\t\t\t\t'done' => true,
\t\t\t);
\t\t}

\t\t$status = self::get_user_verification_status( $user->ID );
\t\t$data   = array(
\t\t\tarray(
\t\t\t\t'name'  => __( 'Verification status', 'argentwolf-email-verification' ),
\t\t\t\t'value' => $status,
\t\t\t),
\t\t);

\t\t$sent_at = (int) get_user_meta( $user->ID, self::META_SENT_AT, true );
\t\tif ( $sent_at > 0 ) {
\t\t\t$data[] = array(
\t\t\t\t'name'  => __( 'Last verification message requested', 'argentwolf-email-verification' ),
\t\t\t\t'value' => gmdate( 'c', $sent_at ),
\t\t\t);
\t\t}

\t\t$expires = (int) get_user_meta( $user->ID, self::META_TOKEN_EXPIRES, true );
\t\tif ( $expires > 0 ) {
\t\t\t$data[] = array(
\t\t\t\t'name'  => __( 'Current verification link expiration', 'argentwolf-email-verification' ),
\t\t\t\t'value' => gmdate( 'c', $expires ),
\t\t\t);
\t\t}

\t\treturn array(
\t\t\t'data' => array(
\t\t\t\tarray(
\t\t\t\t\t'group_id'    => 'argentwolf-email-verification',
\t\t\t\t\t'group_label' => __( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ),
\t\t\t\t\t'item_id'     => 'user-' . $user->ID,
\t\t\t\t\t'data'        => $data,
\t\t\t\t),
\t\t\t),
\t\t\t'done' => true,
\t\t);
\t}

\tpublic static function register_personal_data_eraser( array $erasers ): array {
\t\t$erasers['argentwolf-email-verification'] = array(
\t\t\t'eraser_friendly_name' => __( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ),
\t\t\t'callback'             => array( __CLASS__, 'erase_personal_data' ),
\t\t);

\t\treturn $erasers;
\t}

\tpublic static function erase_personal_data( string $email_address, int $page = 1 ): array {
\t\tunset( $page );

\t\t$user = get_user_by( 'email', $email_address );
\t\tif ( ! ( $user instanceof WP_User ) ) {
\t\t\treturn array(
\t\t\t\t'items_removed'  => false,
\t\t\t\t'items_retained' => false,
\t\t\t\t'messages'       => array(),
\t\t\t\t'done'           => true,
\t\t\t);
\t\t}

\t\t$removed = false;
\t\tforeach ( array( self::META_TOKEN_HASH, self::META_TOKEN_EXPIRES, self::META_SENT_AT ) as $meta_key ) {
\t\t\tif ( metadata_exists( 'user', $user->ID, $meta_key ) ) {
\t\t\t\tdelete_user_meta( $user->ID, $meta_key );
\t\t\t\t$removed = true;
\t\t\t}
\t\t}

\t\treturn array(
\t\t\t'items_removed'  => $removed,
\t\t\t'items_retained' => true,
\t\t\t'messages'       => array(
\t\t\t\t__( 'Verification status was retained because removing it could activate a pending account or change account-access security. A pending user must request a fresh verification message after token metadata is erased.', 'argentwolf-email-verification' ),
\t\t\t),
\t\t\t'done'           => true,
\t\t);
\t}"""
    privacy_methods = privacy_methods.replace("\\t", "\t")
    text = insert_before_method(text, "register_settings", privacy_methods)

    settings_page = """\tpublic static function add_settings_page(): void {
\t\tadd_options_page(
\t\t\t__( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ),
\t\t\t__( 'Email Verification', 'argentwolf-email-verification' ),
\t\t\t'manage_options',
\t\t\tself::SETTINGS_PAGE,
\t\t\tarray( __CLASS__, 'render_settings_page' )
\t\t);
\t}"""
    text = replace_method(text, "add_settings_page", settings_page)

    action_links = """\tpublic static function add_plugin_action_links( array $links ): array {
\t\t$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_PAGE ) ) . '">' . esc_html__( 'Settings', 'argentwolf-email-verification' ) . '</a>';
\t\t$project_link  = '<a href="' . esc_url( self::PROJECT_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'GitHub', 'argentwolf-email-verification' ) . '</a>';
\t\tarray_unshift( $links, $settings_link );
\t\t$links[] = $project_link;
\t\treturn $links;
\t}"""
    text = replace_method(text, "add_plugin_action_links", action_links)

    render_settings = """\tpublic static function render_settings_page(): void {
\t\tif ( ! current_user_can( 'manage_options' ) ) {
\t\t\treturn;
\t\t}
\t\t$pending  = self::pending_account_count();
\t\t$next_run = wp_next_scheduled( self::CRON_HOOK );
\t\t?>
\t\t<div class="wrap">
\t\t\t<h1><?php echo esc_html__( 'ArgentWolf Email Verification', 'argentwolf-email-verification' ); ?></h1>
\t\t\t<form method="post" action="options.php">
\t\t\t\t<?php
\t\t\t\tsettings_fields( 'wrav_ev_settings_group' );
\t\t\t\tdo_settings_sections( self::SETTINGS_PAGE );
\t\t\t\tsubmit_button();
\t\t\t\t?>
\t\t\t</form>
\t\t\t<hr>
\t\t\t<h2><?php echo esc_html__( 'Cleanup status', 'argentwolf-email-verification' ); ?></h2>
\t\t\t<p>
\t\t\t\t<?php
\t\t\t\techo esc_html(
\t\t\t\t\tsprintf(
\t\t\t\t\t\t/* translators: %d is the number of currently pending accounts. */
\t\t\t\t\t\t_n( '%d account is currently pending.', '%d accounts are currently pending.', $pending, 'argentwolf-email-verification' ),
\t\t\t\t\t\t$pending
\t\t\t\t\t)
\t\t\t\t);
\t\t\t\t?>
\t\t\t</p>
\t\t\t<p>
\t\t\t\t<?php
\t\t\t\tif ( $next_run ) {
\t\t\t\t\techo esc_html(
\t\t\t\t\t\tsprintf(
\t\t\t\t\t\t\t/* translators: %s is a localized date and time. */
\t\t\t\t\t\t\t__( 'Next scheduled cleanup: %s', 'argentwolf-email-verification' ),
\t\t\t\t\t\t\twp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run )
\t\t\t\t\t\t)
\t\t\t\t\t);
\t\t\t\t} else {
\t\t\t\t\techo esc_html__( 'No cleanup event is currently scheduled.', 'argentwolf-email-verification' );
\t\t\t\t}
\t\t\t\t?>
\t\t\t</p>
\t\t\t<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
\t\t\t\t<input type="hidden" name="action" value="wrav_ev_run_cleanup">
\t\t\t\t<?php wp_nonce_field( 'wrav_ev_run_cleanup' ); ?>
\t\t\t\t<?php submit_button( __( 'Run cleanup now', 'argentwolf-email-verification' ), 'secondary', 'submit', false ); ?>
\t\t\t</form>
\t\t\t<hr>
\t\t\t<h2><?php echo esc_html__( 'Support development', 'argentwolf-email-verification' ); ?></h2>
\t\t\t<p><?php echo esc_html__( 'ArgentWolf Email Verification is free and open source. Use the project repository to report issues, contribute improvements, and support further development.', 'argentwolf-email-verification' ); ?></p>
\t\t\t<p>
\t\t\t\t<a class="button button-secondary" href="<?php echo esc_url( self::PROJECT_URL ); ?>" target="_blank" rel="noopener noreferrer">
\t\t\t\t\t<?php echo esc_html__( 'View project on GitHub', 'argentwolf-email-verification' ); ?>
\t\t\t\t</a>
\t\t\t</p>
\t\t</div>
\t\t<?php
\t}"""
    text = replace_method(text, "render_settings_page", render_settings)

    bootstrap = re.search(r"(?m)^register_activation_hook\( __FILE__,", text)
    if not bootstrap:
        fail("could not locate the plugin bootstrap hooks")
    if "function argentwolf_email_verification_is_user_verified" in text:
        fail("global public API already exists unexpectedly")

    public_api = """/**
 * Determine whether an existing WordPress user is verified.
 */
function argentwolf_email_verification_is_user_verified( int $user_id ): bool {
\treturn ArgentWolf_Email_Verification::is_user_verified( $user_id );
}

/**
 * Return verified, pending, or unknown for a WordPress user ID.
 */
function argentwolf_email_verification_get_user_verification_status( int $user_id ): string {
\treturn ArgentWolf_Email_Verification::get_user_verification_status( $user_id );
}

"""
    text = text[: bootstrap.start()] + public_api + text[bootstrap.start() :]

    required_markers = (
        "Plugin Name: ArgentWolf Email Verification",
        "Version: 0.3.0",
        "Text Domain: argentwolf-email-verification",
        "final class ArgentWolf_Email_Verification",
        "function argentwolf_email_verification_is_user_verified",
        "function argentwolf_email_verification_get_user_verification_status",
        "Support development",
        "wp_privacy_personal_data_exporters",
        "argentwolf_email_verification_user_verified",
    )
    for marker in required_markers:
        if marker not in text:
            fail(f"generated source is missing required marker: {marker}")

    forbidden = (
        "Plugin Name: Wolf & Raven Local Email Verification",
        "Text Domain: wolf-raven-email-verification",
        "final class WRAV_Local_Email_Verification",
    )
    for marker in forbidden:
        if marker in text:
            fail(f"generated source still contains obsolete marker: {marker}")

    if text.count("do_action( 'wrav_ev_user_verified'") != 1:
        fail("generated source must contain exactly one legacy verification action")
    if text.count("argentwolf_email_verification_after_verification_url") != 2:
        fail("generated source must contain exactly two canonical redirect filters")

    return text.rstrip() + "\n"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if not args.source.is_file():
        fail(f"source file does not exist: {args.source}")
    if args.source.resolve() == args.destination.resolve():
        fail("source and destination must be different files")

    source = args.source.read_text(encoding="utf-8")
    output = build(source)
    args.destination.parent.mkdir(parents=True, exist_ok=True)
    args.destination.write_text(output, encoding="utf-8")
    print(f"Built canonical source: {args.destination}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
