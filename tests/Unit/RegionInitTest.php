<?php

declare(strict_types=1);

use OpenSim\Installer\Grid\SimConfig;
use OpenSim\Installer\Grid\SimPlan;

it('enables OSSL for the owner of the estate in the config of a simulator', function () {
    $plan = new SimPlan();
    $plan->simName = 'Alpha';
    $plan->gridName = 'Test';
    $config = (new SimConfig())->render($plan);
    $ini = parse_ini_string($config, true, INI_SCANNER_RAW);

    expect($ini['OSSL']['Include-osslDefaultEnable'])->toContain('/config-include/osslDefaultEnable.ini');
});

it('has an initialization script to customize, using the functions the config allows', function () {
    $script = file_get_contents(dirname(__DIR__, 2) . '/share/region-init/init-region.lsl');

    expect($script)->toContain('osSetParcelDetails')->toContain('PARCEL_DETAILS_NAME');
});

it('enables OSSL in the config of a grid, with what the initialization script needs', function () {
    $bin = sys_get_temp_dir() . '/ossl-' . uniqid();
    mkdir("$bin/config-include", 0777, true);
    file_put_contents("$bin/config-include/osslDefaultEnable.ini", "[OSSL]\n    Include-osslEnable = \"config-include/osslEnable.ini\"\n");
    file_put_contents("$bin/config-include/osslEnable.ini", "[OSSL]\n    AllowOSFunctions = false\n    ; Allow_osSetParcelDetails = true\n");
    $grid = "$bin/grid";
    (new OpenSim\Installer\Grid\GridShared())->prepare($grid, $bin, false);

    $default = file_get_contents("$grid/config-include/osslDefaultEnable.ini");
    $enable = OpenSim\Installer\Ini::load("$grid/config-include/osslEnable.ini");
    expect($default)->toContain("\"$grid/config-include/osslEnable.ini\"")
        ->and($enable->get('OSSL', 'AllowOSFunctions'))->toBe('true')
        ->and($enable->get('OSSL', 'Allow_osSetParcelDetails'))->toBe('ESTATE_OWNER,PARCEL_OWNER');
});
