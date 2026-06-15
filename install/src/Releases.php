<?php

declare(strict_types=1);

namespace OpenSim\Installer;

/**
 * OpenSimulator release catalogue: fetches (and caches) the published release
 * list, and flags which versions are already installed locally.
 */
final class Releases
{
    private const URL = 'http://opensimulator.org/dist';
    private const TTL = 604800; // 7 days; the list changes once a year or two

    /** @var list<string> candidate core roots used to detect installed versions */
    private array $coreRoots;

    /**
     * @param list<string>|null $coreRoots
     */
    public function __construct(?array $coreRoots = null)
    {
        $home = getenv('HOME') ?: '';
        $this->coreRoots = $coreRoots ?? array_filter([
            '/usr/local/share/opensim',
            $home !== '' ? "$home/opensim/core" : null,
            dirname(__DIR__, 2) . '/core',
        ]);
    }

    /**
     * @return list<array{version:string, tarball:string, installed:bool}>
     */
    public function available(): array
    {
        $html = $this->fetch();
        if ($html === '') {
            return [];
        }

        preg_match_all('/href="(opensim-[0-9][^"]*\.tar\.gz)"/', $html, $m);
        $tarballs = array_values(array_unique(
            array_filter($m[1], static fn (string $f): bool => !str_contains($f, 'source'))
        ));
        rsort($tarballs); // newest first

        $releases = [];
        foreach ($tarballs as $tarball) {
            $name = (string) preg_replace('/\.tar\.gz$/', '', $tarball);   // opensim-0.9.3.0
            $version = (string) preg_replace('/^opensim-/', '', $name);
            $releases[] = [
                'version' => $version,
                'tarball' => self::URL . '/' . $tarball,
                'installed' => $this->isInstalled($name),
            ];
        }

        return $releases;
    }

    private function isInstalled(string $name): bool
    {
        foreach ($this->coreRoots as $root) {
            if (is_file("$root/$name/bin/OpenSim.exe")) {
                return true;
            }
        }

        return false;
    }

    private function fetch(): string
    {
        $cache = $this->cacheFile();

        if (is_file($cache) && (time() - (int) filemtime($cache)) < self::TTL) {
            return (string) file_get_contents($cache);
        }

        $html = @file_get_contents(self::URL . '/');
        if ($html === false || $html === '') {
            // Network failed: fall back to a stale cache if we have one.
            return is_file($cache) ? (string) file_get_contents($cache) : '';
        }

        @mkdir(dirname($cache), 0o755, true);
        @file_put_contents($cache, $html);

        return $html;
    }

    private function cacheFile(): string
    {
        $base = getenv('XDG_CACHE_HOME') ?: (getenv('HOME') . '/.cache');

        return "$base/opensim/releases.html";
    }
}
