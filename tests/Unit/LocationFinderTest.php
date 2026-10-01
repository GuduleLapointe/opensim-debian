<?php
/**
 * Where a new region goes among the ones of a grid: the free place nearest to a
 * point, so that regions added one after the other fill a disc around it.
 */

use OpenSim\Installer\Grid\LocationFinder;

/**
 * Places taken, from a list of [x, y].
 *
 * @param array $places Places.
 * @return array<string,true>
 */
function taken( array $places ) {
	$used = array();
	foreach ( $places as [ $x, $y ] ) {
		LocationFinder::take( $used, $x, $y );
	}

	return $used;
}

describe( 'LocationFinder nearest free place', function () {
	test( 'is the point itself when it is free', function () {
		expect( LocationFinder::nearestFree( array(), 40, 60 ) )->toBe( array( 40, 60 ) );
	} );

	test( 'turns from the east around a taken place', function () {
		$used = taken( array( array( 10, 10 ) ) );
		$order = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$place    = LocationFinder::nearestFree( $used, 10, 10 );
			$order[]  = implode( ',', $place );
			$used    += taken( array( $place ) );
		}

		expect( $order )->toBe( array( '11,10', '10,11', '9,10', '10,9' ) );
	} );

	test( 'fills a disc around the point', function () {
		$used = array();
		$max  = 0;
		for ( $i = 0; $i < 21; $i++ ) {
			[ $x, $y ] = LocationFinder::nearestFree( $used, 50, 50 );
			$max       = max( $max, ( $x - 50 ) ** 2 + ( $y - 50 ) ** 2 );
			$used     += taken( array( array( $x, $y ) ) );
		}

		// 21 places are the ones within a distance of the square root of 5
		expect( $max )->toBe( 5 );
	} );

	test( 'leaves free blocks around it when asked', function () {
		expect( LocationFinder::nearestFree( taken( array( array( 10, 10 ) ) ), 10, 10, 1 ) )->toBe( array( 12, 10 ) );
	} );

	test( 'keeps its distance from every place taken, far from everything', function () {
		$used  = taken( array( array( 10, 10 ), array( 11, 10 ), array( 10, 11 ) ) );
		$place = LocationFinder::nearestFree( $used, 10, 10, LocationFinder::GAPS['far'] );

		foreach ( array( array( 10, 10 ), array( 11, 10 ), array( 10, 11 ) ) as [ $x, $y ] ) {
			expect( max( abs( $place[0] - $x ), abs( $place[1] - $y ) ) )->toBeGreaterThan( LocationFinder::GAPS['far'] );
		}
	} );

	test( 'never goes below zero', function () {
		expect( LocationFinder::nearestFree( taken( array( array( 0, 0 ), array( 1, 0 ), array( 0, 1 ) ) ), 0, 0 ) )->toBe( array( 1, 1 ) );
	} );

	test( 'avoids the places a large region takes', function () {
		$used = array();
		LocationFinder::take( $used, 5, 5, 2, 2 );

		expect( $used )->toHaveCount( 4 );
		// The place west of the large region is the nearest free one, the east one is inside it
		expect( LocationFinder::nearestFree( $used, 5, 5 ) )->toBe( array( 4, 5 ) );
	} );
} );

describe( 'LocationFinder places', function () {
	test( 'the center is the middle of the places taken', function () {
		expect( LocationFinder::center( taken( array( array( 0, 0 ), array( 10, 10 ) ) ) ) )->toBe( array( 5, 5 ) );
		expect( LocationFinder::center( array() ) )->toBeNull();
	} );

	test( 'a location is read as x,y', function () {
		expect( LocationFinder::parse( '1000, 1002' ) )->toBe( array( 1000, 1002 ) );
		expect( LocationFinder::parse( 'a,b' ) )->toBeNull();
	} );
} );
