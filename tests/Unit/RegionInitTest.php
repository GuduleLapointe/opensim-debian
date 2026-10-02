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

    expect($ini['OSSL']['AllowOSFunctions'])->toBe('true')
        ->and($ini['OSSL']['Allow_osSetParcelDetails'])->toContain('ESTATE_OWNER');
});

it('has an initialization script to customize, using the functions the config allows', function () {
    $script = file_get_contents(dirname(__DIR__, 2) . '/share/region-init/init-region.lsl');

    expect($script)->toContain('osSetParcelDetails')->toContain('PARCEL_DETAILS_NAME');
});
