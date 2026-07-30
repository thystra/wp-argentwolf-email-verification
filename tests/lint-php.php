<?php
/**
 * Lint every project-owned PHP source file.
 *
 * File: tests/lint-php.php
 */

$root     = dirname( __DIR__ );
$failures = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator(
		$root,
		FilesystemIterator::SKIP_DOTS
	)
);

foreach ( $iterator as $file ) {
	if ( ! $file instanceof SplFileInfo || 'php' !== $file->getExtension() ) {
		continue;
	}

	$path = $file->getPathname();

	if (
		str_contains( $path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR )
		|| str_contains( $path, DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR )
	) {
		continue;
	}

	$command = sprintf( 'php -l %s 2>&1', escapeshellarg( $path ) );
	exec( $command, $output, $status );

	if ( 0 !== $status ) {
		$failures[] = implode( "\n", $output );
	}

	$output = array();
}

if ( array() !== $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, $failure . "\n" );
	}

	exit( 1 );
}

fwrite( STDOUT, "All PHP files passed syntax validation.\n" );

// EOF: tests/lint-php.php
