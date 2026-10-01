<?php
/**
 * The shell scripts, tested with bashunit (tests/lib/bashunit, the *-test.sh
 * files of tests/Unit), so that one command runs every test: vendor/bin/pest.
 */

$root    = dirname( __DIR__, 2 );
$bash    = trim( (string) shell_exec( 'command -v bash' ) );
$missing = ! $bash ? 'bash is not available here (lerd php:pkg add bash)' : ( ! is_file( "$root/tests/lib/bashunit" ) ? 'tests/lib/bashunit is missing' : '' );

describe( 'Shell scripts', function () use ( $root, $bash, $missing ) {
	test( 'bashunit suites pass', function () use ( $root, $bash ) {
		$process = proc_open(
			array( $bash, "$root/tests/lib/bashunit", "$root/tests/Unit" ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes,
			$root
		);
		$output  = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		$status  = proc_close( $process );

		expect( $status, $output )->toBe( 0 );
	} )->skip( '' !== $missing, $missing );
} );
