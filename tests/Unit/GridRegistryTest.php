<?php
/**
 * Where a region of a grid elsewhere goes when its regions are not known here.
 */

use OpenSim\Installer\Grid\GridRegistry;

describe( 'GridRegistry scattered location', function () {
	test( 'stays in the 1000 to 1999 square', function () {
		foreach ( array( '607c44e9-3d01-45eb-a07e-937dc72dbadb', '00000000-0000-0000-0000-000000000000', 'ffffffff-ffff-ffff-ffff-ffffffffffff' ) as $uuid ) {
			[ $x, $y ] = array_map( 'intval', explode( ',', GridRegistry::scatteredLocation( $uuid ) ) );

			expect( $x )->toBeBetween( 1000, 1999 );
			expect( $y )->toBeBetween( 1000, 1999 );
		}
	} );

	test( 'is the same for the same region', function () {
		$uuid = '607c44e9-3d01-45eb-a07e-937dc72dbadb';

		expect( GridRegistry::scatteredLocation( $uuid ) )->toBe( GridRegistry::scatteredLocation( $uuid ) );
	} );

	test( 'is not the place everybody takes, and differs between regions', function () {
		$places = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$places[] = GridRegistry::scatteredLocation( sprintf( '%08x-0000-4000-8000-000000000000', $i * 2654435761 % 4294967296 ) );
		}

		expect( $places )->not->toContain( '1000,1000' );
		expect( array_unique( $places ) )->toHaveCount( 20 );
	} );
} );
