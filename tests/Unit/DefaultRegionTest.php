<?php
/**
 * The default region of a grid: one name asked in the grid setup, written in
 * the Robust config as the key Robust looks for, read back from it.
 */

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\GridPlan;
use OpenSim\Installer\Grid\RegionName;
use OpenSim\Installer\Grid\RobustConfig;

/**
 * The Robust config a plan generates, from a minimal example.
 *
 * @param GridPlan $plan The plan.
 * @return string Generated Robust.HG.ini.
 */
function robust_generated( GridPlan $plan ) {
	$bin = sys_get_temp_dir() . '/default-region-' . bin2hex( random_bytes( 4 ) );
	mkdir( $bin );
	file_put_contents( "$bin/Robust.HG.ini.example", "[Const]\n[GridService]\n    ; Region_Welcome_Area = \"DefaultRegion, DefaultHGRegion\"\n" );
	$plan->binDir = $bin;
	$ini          = ( new RobustConfig() )->generate( $plan );
	unlink( "$bin/Robust.HG.ini.example" );
	rmdir( $bin );

	return $ini;
}

describe( 'RegionName', function () {
	test( 'accepts what a region can be called and refuses the rest', function () {
		expect( RegionName::problem( 'Welcome' ) )->toBeNull();
		expect( RegionName::problem( 'Welcome Area 2' ) )->toBeNull();
		expect( RegionName::problem( '' ) )->not->toBeNull();
		expect( RegionName::problem( 'Bad"name' ) )->not->toBeNull();
	} );

	test( 'is the key of the flags with the spaces replaced by underscores, and back', function () {
		expect( RegionName::configKey( 'Welcome' ) )->toBe( 'Region_Welcome' );
		expect( RegionName::configKey( ' Welcome Area ' ) )->toBe( 'Region_Welcome_Area' );
		expect( RegionName::fromConfigKey( 'Region_Welcome_Area' ) )->toBe( 'Welcome Area' );
	} );

	test( 'is known among names the way Robust compares them', function () {
		$known = array( 'welcome area' => true, 'sandbox' => true );

		expect( RegionName::isIn( 'Welcome Area', $known ) )->toBeTrue();
		expect( RegionName::isIn( 'Welcome_Area', $known ) )->toBeTrue();
		expect( RegionName::isIn( 'Welcome', $known ) )->toBeFalse();
	} );
} );

describe( 'Default region in the Robust config', function () {
	test( 'is Welcome unless the setup says otherwise', function () {
		$plan = new GridPlan();
		$plan->baseHostname = 'localhost';

		expect( $plan->defaultRegion )->toBe( 'Welcome' );
		expect( robust_generated( $plan ) )->toContain( 'Region_Welcome = "DefaultRegion, DefaultHGRegion, FallbackRegion, Persistent"' );
	} );

	test( 'is the one chosen, under the key Robust looks for, and only that one', function () {
		$plan                = new GridPlan();
		$plan->baseHostname  = 'localhost';
		$plan->defaultRegion = 'Velcome Area';
		$ini                 = robust_generated( $plan );

		expect( $ini )->toContain( 'Region_Velcome_Area = "DefaultRegion, DefaultHGRegion, FallbackRegion, Persistent"' );
		expect( $ini )->not->toMatch( '/^\s*Region_Welcome\b/m' );
	} );

	test( 'is read back from the config of the grid', function () {
		$plan                = new GridPlan();
		$plan->baseHostname  = 'localhost';
		$plan->defaultRegion = 'Velcome Area';
		$file                = tempnam( sys_get_temp_dir(), 'robust' );
		file_put_contents( $file, robust_generated( $plan ) );
		$read = GridInfo::parse( $file );
		unlink( $file );

		expect( $read['defaultRegion'] )->toBe( 'Velcome Area' );
	} );

	test( 'is not found in a config where the example line is still commented', function () {
		$file = tempnam( sys_get_temp_dir(), 'robust' );
		file_put_contents( $file, "[GridService]\n    ; Region_Welcome_Area = \"DefaultRegion, DefaultHGRegion\"\n    Region_Other = \"Persistent\"\n" );
		$read = GridInfo::parse( $file );
		unlink( $file );

		expect( $read )->not->toHaveKey( 'defaultRegion' );
	} );
} );

describe( 'Default region comes first', function () {
	test( 'is due on a grid run from this machine until a region of that name exists', function () {
		$grid                = new GridInfo();
		$grid->defaultRegion = 'Welcome';

		expect( $grid->defaultRegionDue( array() ) )->toBeTrue();
		expect( $grid->defaultRegionDue( array( 'sandbox' => true ) ) )->toBeTrue();
		expect( $grid->defaultRegionDue( array( 'welcome' => true, 'sandbox' => true ) ) )->toBeFalse();
	} );

	test( 'is never due on a grid run elsewhere, nor on one that has no default region', function () {
		$remote                = new GridInfo();
		$remote->remote        = true;
		$remote->defaultRegion = 'Welcome';
		$none                  = new GridInfo();

		expect( $remote->defaultRegionDue( array() ) )->toBeFalse();
		expect( $none->defaultRegionDue( array() ) )->toBeFalse();
	} );
} );
