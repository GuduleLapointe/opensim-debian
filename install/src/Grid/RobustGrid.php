<?php

declare(strict_types=1);

namespace OpenSim\Installer\Grid;

/**
 * What the grid service of a Robust tells of its regions, over HTTP: no database
 * access is needed, so it works for a grid whose Robust is on another machine.
 * It is asked on the private port of Robust, which every simulator of the grid
 * has to reach to register its regions.
 */
final class RobustGrid
{
    private const NO_SCOPE = '00000000-0000-0000-0000-000000000000';

    /**
     * The places taken around a place, within $radius blocks.
     *
     * @return array<string,true>|null null when the grid does not answer
     */
    public static function locations(string $host, int $port, int $x, int $y, int $radius = 40): ?array
    {
        $answer = self::ask($host, $port, [
            'METHOD' => 'get_region_range',
            'SCOPEID' => self::NO_SCOPE,
            'XMIN' => max(0, $x - $radius) * 256,
            'XMAX' => ($x + $radius) * 256,
            'YMIN' => max(0, $y - $radius) * 256,
            'YMAX' => ($y + $radius) * 256,
        ]);
        if ($answer === null) {
            return null;
        }
        $used = [];
        foreach (self::regions($answer) as [$rx, $ry, $width, $height]) {
            LocationFinder::take($used, $rx, $ry, $width, $height);
        }

        return $used;
    }

    /**
     * Whether the grid has a region of this name (the grid compares names without
     * the case).
     *
     * @return bool|null null when the grid does not answer
     */
    public static function hasRegion(string $host, int $port, string $name): ?bool
    {
        $answer = self::ask($host, $port, [
            'METHOD' => 'get_region_by_name',
            'SCOPEID' => self::NO_SCOPE,
            'NAME' => $name,
        ]);

        return $answer === null ? null : self::named($answer);
    }

    /** Whether an answer to get_region_by_name describes a region. */
    public static function named(string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document !== false && isset($document->result->uuid);
    }

    /**
     * The regions of an answer of the grid service: their place, in blocks, and
     * the blocks they take.
     *
     * @return list<array{0:int,1:int,2:int,3:int}>
     */
    public static function regions(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($document === false) {
            return [];
        }
        $regions = [];
        foreach ($document->children() as $name => $region) {
            if (str_starts_with((string) $name, 'region') && isset($region->locX, $region->locY)) {
                $regions[] = [
                    intdiv((int) $region->locX, 256),
                    intdiv((int) $region->locY, 256),
                    max(1, intdiv((int) ($region->sizeX ?? 256), 256)),
                    max(1, intdiv((int) ($region->sizeY ?? 256), 256)),
                ];
            }
        }

        return $regions;
    }

    /** The answer of the grid service to a request, null when it does not answer. */
    private static function ask(string $host, int $port, array $fields): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($fields),
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents("http://$host:$port/grid", false, $context);

        return $body === false || $body === '' ? null : $body;
    }
}
