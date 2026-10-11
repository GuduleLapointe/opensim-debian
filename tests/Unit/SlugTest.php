<?php

use OpenSim\Installer\Grid\GridInfo;
use OpenSim\Installer\Grid\Slug;

test('keeps the words apart, in snake_case', function (string $name, string $slug) {
    expect(Slug::slug($name))->toBe($slug)->and(Slug::nick($name))->toBe($slug);
})->with([
    ['The Rapist', 'the_rapist'],
    ['Therapist', 'therapist'],
    ['Car Pet', 'car_pet'],
    ['Carpet', 'carpet'],
    ['Man Age', 'man_age'],
    ['Manage', 'manage'],
    ["Olivier's grid", 'oliviers_grid'],
    ['OliviersGrid', 'oliviers_grid'],
    ['Joyeux Noël', 'joyeux_noel'],
    ['  --Weird  name!! ', 'weird_name'],
]);

test('gives the instances the same names', function () {
    expect(GridInfo::instanceName('The Rapist_Sim 1'))
        ->toBe('the_rapist_sim_1')
        ->and(GridInfo::instanceName('testgrid_sim1'))
        ->toBe('testgrid_sim1');
});
