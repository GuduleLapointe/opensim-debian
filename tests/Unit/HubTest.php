<?php
/**
 * The screens of the setup, one level deeper each time: home, grid, simulator,
 * region. A scripted user chooses, the screens shown are recorded, the files
 * are real ones in a temporary tree.
 */

use OpenSim\Installer\Hub;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * An interface that answers the choices from a script and records the screens.
 */
final class RecordingUi implements InstallerUi
{
	/** @var array<int,array{label:string,keys:list<string>,default:?string}> */
	public array $screens = array();
	/** @var list<string> */
	public array $notes = array();

	public function __construct( private array $script ) {}

	public function intro( string $title ): void {}
	public function note( string $message ): void { $this->notes[] = $message; }
	public function warn( string $message ): void { $this->notes[] = "warn: $message"; }
	public function error( string $message ): void { $this->notes[] = "error: $message"; }
	public function outro( string $message ): void {}

	public function choose( string $label, array $options, ?string $default = null, ?string $hint = null ): string {
		$this->screens[] = array(
			'label'   => $label,
			'keys'    => array_keys( $options ),
			'labels'  => array_values( $options ),
			'default' => $default,
		);

		return array_shift( $this->script ) ?? 'quit';
	}

	public function text( string $label, string $default = '', ?Closure $validate = null, ?string $hint = null ): string { return $default; }
	public function secret( string $label, ?Closure $validate = null, ?string $hint = null ): string { return ''; }
	public function confirm( string $label, bool $default = true ): bool { return $default; }
	public function spin( Closure $callback, string $message ): mixed { return $callback(); }
}

/**
 * A tree of setup: two cores' worth of config in a temporary home, a grid with
 * two simulators and three regions, a grid on another machine.
 */
function hub_tree() {
	$root = sys_get_temp_dir() . '/hub-' . bin2hex( random_bytes( 4 ) );
	$etc  = "$root/etc";
	foreach ( array( "$root/home", "$etc/grids/alpha/sims/alpha_sim1/regions", "$etc/grids/alpha/sims/alpha_sim2/regions", "$etc/grids/beta", "$etc/robust.d", "$etc/opensim.d" ) as $dir ) {
		mkdir( $dir, 0o755, true );
	}
	file_put_contents( "$root/home/.opensim.conf", "[Defaults]\nDefaultProfile = 0.9.3.0\n\n[0.9.3.0]\nEtcRoot = $etc\n" );
	file_put_contents( "$etc/grids/alpha/Robust.HG.ini", "[Network]\n" );
	symlink( "$etc/grids/alpha/Robust.HG.ini", "$etc/robust.d/alpha.ini" );
	file_put_contents( "$etc/grids/alpha/sims/alpha_sim1.ini", "[Startup]\n" );
	file_put_contents( "$etc/grids/alpha/sims/alpha_sim2.ini", "[Startup]\n" );
	symlink( "$etc/grids/alpha/sims/alpha_sim1.ini", "$etc/opensim.d/alpha_sim1.ini" );
	file_put_contents( "$etc/grids/alpha/sims/alpha_sim1/regions/Sim1.ini", "[Sim1]\nLocation = 1000,1000\n" );
	file_put_contents( "$etc/grids/alpha/sims/alpha_sim1/regions/Sim1North.ini", "[Sim1North]\nLocation = 1000,1001\n" );
	file_put_contents( "$etc/grids/alpha/sims/alpha_sim1/regions/Far.ini.disabled", "[Far]\nLocation = 1200,1200\n" );
	file_put_contents( "$etc/grids/beta/beta.conf", "[Grid]\nRemote = true\nGridNick = beta\n" );

	return array( $root, $etc );
}

/**
 * Runs the hub with a script of choices, in a tree.
 *
 * @param array $script Choices.
 * @return array The recording interface, the tree root.
 */
function hub_run( array $script ) {
	[ $root, $etc ] = hub_tree();
	$home           = getenv( 'HOME' );
	putenv( "HOME=$root/home" );
	$ui = new RecordingUi( $script );
	try {
		( new Hub( $ui ) )->run();
	} finally {
		putenv( false === $home ? 'HOME' : "HOME=$home" );
	}

	return array( $ui, $etc );
}

describe( 'Hub home', function () {
	test( 'lists the core, each grid with its state, add grid and quit', function () {
		[ $ui ] = hub_run( array( 'quit' ) );

		expect( $ui->screens[0]['keys'] )->toBe( array( 'core', 'grid:alpha', 'grid:beta', 'add', 'quit' ) );
		expect( $ui->screens[0]['labels'] )->toBe( array( 'OpenSim core [0.9.3.0]', 'alpha', 'beta (Robust on another machine)', 'Add grid', 'Quit' ) );
		expect( $ui->screens[0]['default'] )->toBe( 'grid:alpha' );
	} );
} );

describe( 'Hub grid', function () {
	test( 'lists configure, its simulators, add simulator, disable and back', function () {
		[ $ui ] = hub_run( array( 'grid:alpha', 'back', 'quit' ) );

		expect( $ui->screens[1]['label'] )->toBe( 'Grid: alpha' );
		expect( $ui->screens[1]['keys'] )->toBe( array( 'configure', 'sim:alpha_sim1', 'sim:alpha_sim2', 'addsim', 'toggle', 'back' ) );
		expect( $ui->screens[1]['labels'] )->toBe( array( 'Configure', 'alpha_sim1', 'alpha_sim2 [disabled]', 'Add simulator', 'Disable', 'Back' ) );
	} );

	test( 'a grid on another machine has its simulators only', function () {
		[ $ui ] = hub_run( array( 'grid:beta', 'back', 'quit' ) );

		expect( $ui->screens[1]['keys'] )->toBe( array( 'addsim', 'back' ) );
	} );
} );

describe( 'Hub simulator and region', function () {
	test( 'a simulator lists reconfigure, enable or disable, its regions and add region', function () {
		[ $ui ] = hub_run( array( 'grid:alpha', 'sim:alpha_sim1', 'back', 'back', 'quit' ) );

		expect( $ui->screens[2]['label'] )->toBe( 'Simulator: alpha_sim1' );
		expect( $ui->screens[2]['keys'] )->toBe( array( 'reconfigure', 'toggle', 'region:Far', 'region:Sim1', 'region:Sim1North', 'addregion', 'back' ) );
		expect( $ui->screens[2]['labels'] )->toBe( array( 'Reconfigure', 'Disable', 'Far [disabled]', 'Sim1', 'Sim1North', 'Add region', 'Back' ) );
	} );

	test( 'a region disabled and enabled again renames its file', function () {
		[ $ui, $etc ] = hub_run( array( 'grid:alpha', 'sim:alpha_sim1', 'region:Sim1', 'toggle', 'toggle', 'back', 'back', 'back', 'quit' ) );
		$regions = "$etc/grids/alpha/sims/alpha_sim1/regions";

		expect( $ui->screens[3]['labels'] )->toBe( array( 'Reconfigure', 'Disable', 'Back' ) );
		expect( $ui->screens[4]['labels'] )->toBe( array( 'Reconfigure', 'Enable', 'Back' ) );
		expect( $ui->screens[5]['labels'] )->toBe( array( 'Reconfigure', 'Disable', 'Back' ) );
		expect( is_file( "$regions/Sim1.ini" ) )->toBeTrue();
		expect( is_file( "$regions/Sim1.ini.disabled" ) )->toBeFalse();
	} );

	test( 'a simulator disabled from its screen loses its link', function () {
		[ , $etc ] = hub_run( array( 'grid:alpha', 'sim:alpha_sim1', 'toggle', 'back', 'back', 'quit' ) );

		expect( file_exists( "$etc/opensim.d/alpha_sim1.ini" ) || is_link( "$etc/opensim.d/alpha_sim1.ini" ) )->toBeFalse();
	} );
} );

describe( 'RegionState', function () {
	test( 'reads what a region file holds', function () {
		[ , $etc ] = hub_tree();
		file_put_contents( "$etc/Far.ini", "[Far]\nRegionUUID = 607c44e9-3d01-45eb-a07e-937dc72dbadb\nLocation = 1200,1200\nSizeX = 512\nInternalPort = 8015\nExternalHostName = \"play.example.org\"\n" );

		expect( \OpenSim\Installer\Grid\RegionState::values( "$etc/Far.ini" ) )->toBe(
			array(
				'RegionUUID'       => '607c44e9-3d01-45eb-a07e-937dc72dbadb',
				'Location'         => '1200,1200',
				'SizeX'            => '512',
				'InternalPort'     => '8015',
				'ExternalHostName' => 'play.example.org',
			)
		);
	} );

	test( 'does not enable a region over one of the same name', function () {
		[ , $etc ] = hub_tree();
		$regions = "$etc/grids/alpha/sims/alpha_sim1/regions";
		file_put_contents( "$regions/Far.ini", "[Far]\n" );

		expect( \OpenSim\Installer\Grid\RegionState::enable( "$regions/Far.ini.disabled" ) )->toBeNull();
	} );
} );

describe( 'GridRegistry region names', function () {
	test( 'are the ones of every simulator of the grid, disabled regions included, without the case', function () {
		[ , $etc ] = hub_tree();

		expect( array_keys( \OpenSim\Installer\Grid\GridRegistry::fileNames( "$etc/grids/alpha" ) ) )->toBe( array( 'far', 'sim1', 'sim1north' ) );
	} );
} );
