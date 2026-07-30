<?php
/**
 * WordPress integration-test bootstrap.
 *
 * File: tests/bootstrap-integration.php
 */

$project_root = dirname( __DIR__ );
$tests_dir    = getenv( 'WP_TESTS_DIR' );

if ( false === $tests_dir || '' === $tests_dir ) {
	$tests_dir = '/tmp/wordpress-tests-lib';
}

$functions_file = $tests_dir . '/includes/functions.php';
$bootstrap_file = $tests_dir . '/includes/bootstrap.php';

if ( ! is_readable( $functions_file ) || ! is_readable( $bootstrap_file ) ) {
	fwrite(
		STDERR,
		"WordPress test library is missing. Run bin/install-wp-tests.sh first.\n"
	);
	exit( 1 );
}

require_once $project_root . '/vendor/autoload.php';
require_once $functions_file;

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $project_root ): void {
		require_once $project_root . '/argentwolf-email-verification.php';
	}
);

require_once $bootstrap_file;

// EOF: tests/bootstrap-integration.php
