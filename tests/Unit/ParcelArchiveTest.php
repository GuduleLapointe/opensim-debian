<?php

declare(strict_types=1);

use OpenSim\Installer\Grid\ParcelArchive;

function parcelRow(): array
{
    $row = array_fill(0, count(ParcelArchive::COLUMNS), '0');
    $row[0] = '11111111-2222-3333-4444-555555555555';
    $row[1] = '1';
    $row[2] = bin2hex(str_repeat("\xff", 8));
    $row[3] = 'Your Parcel';
    $row[4] = '';
    $row[5] = '99999999-8888-7777-6666-555555555555';
    $row[7] = '65536';

    return $row;
}

it('writes a parcel with a new name and its other values', function () {
    $xml = ParcelArchive::parcel(parcelRow(), 'Alpha & Co');
    $doc = simplexml_load_string($xml);

    expect((string) $doc->Name)->toBe('Alpha & Co')
        ->and((string) $doc->UserLocation)->toBe('<0,0,0>')
        ->and((string) $doc->OwnerID->Guid)->toBe('99999999-8888-7777-6666-555555555555')
        ->and((string) $doc->GlobalID->Guid)->toBe('11111111-2222-3333-4444-555555555555')
        ->and(base64_decode((string) $doc->Bitmap))->toBe(str_repeat("\xff", 8))
        ->and((string) $doc->Area)->toBe('65536');
});

it('keeps the name when none is given', function () {
    expect((string) simplexml_load_string(ParcelArchive::parcel(parcelRow()))->Name)->toBe('Your Parcel');
});

it('writes an OAR with the control file and the parcels', function () {
    $path = sys_get_temp_dir() . '/parcel-' . uniqid() . '.oar';
    ParcelArchive::write($path, ['11111111-2222-3333-4444-555555555555' => ParcelArchive::parcel(parcelRow(), 'Alpha')]);

    $names = [];
    foreach (new RecursiveIteratorIterator(new PharData($path)) as $file) {
        $names[] = substr((string) $file->getPathname(), strlen("phar://$path/"));
    }
    sort($names);

    expect($names)->toBe(['archive.xml', 'landdata/11111111-2222-3333-4444-555555555555.xml'])
        ->and(fileperms($path) & 0o777)->toBe(0o644);
    unlink($path);
});

it('asks for the parcels of a region', function () {
    expect(ParcelArchive::query('abc'))->toContain("FROM land WHERE RegionUUID = 'abc'")->toContain('HEX(Bitmap)');
});
