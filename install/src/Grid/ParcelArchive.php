<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * An OAR with the parcels of a region and nothing else, to change them in a running region: loaded with
 * `load oar --merge --force-parcels --skip-assets`, it replaces the parcels without restarting the simulator
 * (a running region does not read its land again from the database).
 *
 * The parcels are the rows of the `land` table, written the way OpenSimulator writes them in an OAR
 * (landdata/<uuid>.xml); only the name is changed.
 */
final class ParcelArchive
{
    /** The columns read, in the order of the values given to parcel() */
    public const COLUMNS = [
        'UUID', 'LocalLandID', 'HEX(Bitmap)', 'Name', 'Description', 'OwnerUUID', 'IsGroupOwned', 'Area',
        'AuctionID', 'Category', 'ClaimDate', 'ClaimPrice', 'GroupUUID', 'SalePrice', 'LandStatus', 'LandFlags',
        'LandingType', 'MediaAutoScale', 'MediaTextureUUID', 'MediaURL', 'MusicURL', 'PassHours', 'PassPrice',
        'SnapshotUUID', 'UserLocationX', 'UserLocationY', 'UserLocationZ', 'UserLookAtX', 'UserLookAtY',
        'UserLookAtZ', 'AuthbuyerID', 'OtherCleanTime', 'Dwell',
    ];

    private const STATUS = ['Leased', 'LeasePending', 'Abandoned'];

    /** The query for the parcels of a region */
    public static function query(string $regionUuid): string
    {
        return 'SELECT ' . implode(',', self::COLUMNS) . " FROM land WHERE RegionUUID = '$regionUuid'";
    }

    /**
     * The XML of a parcel.
     *
     * @param list<string> $row the values of COLUMNS (a line of the query, split on tabs)
     */
    public static function parcel(array $row, ?string $name = null): string
    {
        $v = array_combine(array_map(static fn(string $c): string => preg_replace('/^HEX\((.*)\)$/', '$1', $c), self::COLUMNS), array_pad($row, count(self::COLUMNS), ''));
        $text = static fn(string $s): string => htmlspecialchars($s === 'NULL' ? '' : $s, ENT_XML1 | ENT_QUOTES);
        $uuid = static fn(string $tag, string $s): string => "<$tag><Guid>" . $text($s !== '' ? $s : '00000000-0000-0000-0000-000000000000') . "</Guid></$tag>";
        $bitmap = base64_encode((string) hex2bin($v['Bitmap']));
        $vector = static fn(string $axis): string => '<' . implode(',', array_map(
            static fn(string $a): string => $v["$axis$a"] !== '' ? $v["$axis$a"] : '0',
            ['X', 'Y', 'Z'],
        )) . '>';

        return '<?xml version="1.0" encoding="utf-8"?>' . "\n" . '<LandData>'
            . '<Area>' . $text($v['Area']) . '</Area>'
            . '<AuctionID>' . $text($v['AuctionID']) . '</AuctionID>'
            . $uuid('AuthBuyerID', $v['AuthbuyerID'])
            . '<Category>' . $text($v['Category']) . '</Category>'
            . '<ClaimDate>' . $text($v['ClaimDate']) . '</ClaimDate>'
            . '<ClaimPrice>' . $text($v['ClaimPrice']) . '</ClaimPrice>'
            . $uuid('GlobalID', $v['UUID'])
            . $uuid('GroupID', $v['GroupUUID'])
            . '<IsGroupOwned>' . ($v['IsGroupOwned'] === '1' ? 'true' : 'false') . '</IsGroupOwned>'
            . '<Bitmap>' . $bitmap . '</Bitmap>'
            . '<Description>' . $text($v['Description']) . '</Description>'
            . '<Flags>' . $text($v['LandFlags']) . '</Flags>'
            . '<LandingType>' . $text($v['LandingType']) . '</LandingType>'
            . '<Name>' . $text($name ?? $v['Name']) . '</Name>'
            . '<Status>' . (self::STATUS[(int) $v['LandStatus']] ?? 'Leased') . '</Status>'
            . '<LocalID>' . $text($v['LocalLandID']) . '</LocalID>'
            . '<MediaAutoScale>' . $text($v['MediaAutoScale']) . '</MediaAutoScale>'
            . $uuid('MediaID', $v['MediaTextureUUID'])
            . '<MediaURL>' . $text($v['MediaURL']) . '</MediaURL>'
            . '<MusicURL>' . $text($v['MusicURL']) . '</MusicURL>'
            . $uuid('OwnerID', $v['OwnerUUID'])
            . '<ParcelAccessList />'
            . '<PassHours>' . $text($v['PassHours']) . '</PassHours>'
            . '<PassPrice>' . $text($v['PassPrice']) . '</PassPrice>'
            . '<SalePrice>' . $text($v['SalePrice']) . '</SalePrice>'
            . $uuid('SnapshotID', $v['SnapshotUUID'])
            . '<UserLocation>' . $text($vector('UserLocation')) . '</UserLocation>'
            . '<UserLookAt>' . $text($vector('UserLookAt')) . '</UserLookAt>'
            . '<Dwell>' . $text($v['Dwell']) . '</Dwell>'
            . '<OtherCleanTime>' . $text($v['OtherCleanTime']) . '</OtherCleanTime>'
            . '</LandData>';
    }

    /**
     * Write the OAR (a gzipped tar) with the parcels given, readable by everyone: the simulator reads it as
     * its own user.
     *
     * @param array<string,string> $parcels XML of each parcel, by the UUID of the parcel
     */
    public static function write(string $path, array $parcels): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'oar');
        @unlink($tmp);
        $tar = new \PharData($tmp . '.tar');
        $tar->addFromString(
            'archive.xml',
            '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<archive major_version="0" minor_version="8"><creation_date>' . time() . '</creation_date>'
            . '<assets_included>False</assets_included></archive>',
        );
        foreach ($parcels as $uuid => $xml) {
            $tar->addFromString("landdata/$uuid.xml", $xml);
        }
        $tar->compress(\Phar::GZ);
        rename("$tmp.tar.gz", $path);
        @unlink("$tmp.tar");
        chmod($path, 0o644);
    }
}
