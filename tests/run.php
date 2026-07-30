<?php
/**
 * Dependency-free verification-contract tests.
 *
 * File: tests/run.php
 */

$root     = dirname( __DIR__ );
$failures = array();

$assert = static function ( bool $condition, string $message ) use (
	&$failures
): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$main     = file_get_contents(
	$root . '/argentwolf-email-verification.php'
);
$readme   = file_get_contents( $root . '/readme.txt' );
$composer = json_decode(
	(string) file_get_contents( $root . '/composer.json' ),
	true
);
$workflow = file_get_contents( $root . '/.github/workflows/ci.yml' );
$legacy   = file_get_contents(
	$root . '/docs/legacy-api-deprecation.md'
);

preg_match(
	'/^[\h]*\*[\h]+Version:[\h]*(\S+)[\h]*$/m',
	(string) $main,
	$header
);
preg_match(
	'/^Stable tag:[\h]*(\S+)[\h]*$/m',
	(string) $readme,
	$stable
);

$assert(
	'0.3.4' === ( $header[1] ?? null ),
	'Plugin header version must be 0.3.4.'
);
$assert(
	'0.3.4' === ( $stable[1] ?? null ),
	'Stable Tag must be 0.3.4.'
);
$assert(
	str_contains(
		(string) $main,
		'Plugin Name: ArgentWolf Email Verification'
	),
	'Plugin display name must remain canonical.'
);
$assert(
	str_contains(
		(string) $main,
		'function argentwolf_email_verification_is_user_verified'
	),
	'Boolean verification API must remain public.'
);
$assert(
	str_contains(
		(string) $main,
		'function argentwolf_email_verification_get_user_verification_status'
	),
	'Status verification API must remain public.'
);
$assert(
	str_contains(
		(string) $main,
		"'argentwolf_email_verification_user_verified'"
	),
	'Canonical verified action must remain available.'
);
$assert(
	str_contains( (string) $main, 'private const META_VERIFIED' ),
	'Legacy verification metadata must remain encapsulated.'
);
$assert(
	'>=7.4' === ( $composer['require']['php'] ?? null ),
	'Composer runtime floor must remain PHP 7.4.'
);
$assert(
	'7.4.0' === (
		$composer['config']['platform']['php'] ?? null
	),
	'Composer dependency resolution must target PHP 7.4.'
);
$assert(
	'^9.6.16' === (
		$composer['require-dev']['phpunit/phpunit'] ?? null
	),
	'WordPress 7.0 integration must use PHPUnit 9.6.'
);
$assert(
	str_contains( (string) $workflow, "php-version: '7.4'" ),
	'CI must exercise the declared PHP floor.'
);
$assert(
	str_contains( (string) $workflow, "- '8.5'" ),
	'CI must exercise PHP 8.5.'
);
$assert(
	str_contains(
		(string) $legacy,
		'No removal before 1.0.0'
	),
	'Legacy-hook policy must prohibit pre-1.0 removal.'
);

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "FAIL: {$failure}\n" );
	}

	exit( 1 );
}

fwrite(
	STDOUT,
	"All dependency-free verification-contract tests passed.\n"
);

// EOF: tests/run.php
