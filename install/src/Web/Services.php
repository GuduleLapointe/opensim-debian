<?php

declare(strict_types=1);

namespace OpenSim\Installer\Web;

/**
 * The web services of a grid, the scripts of opensim-helpers that answer them, and the address each has by default:
 * `/helpers/<script>`. The grid says otherwise in the official settings (the addresses of Robust.ini and OpenSim.ini: this is
 * where the helpers read them), `<base>` being the path of its economy, which the viewers complete with the name of the script.
 *
 * The helpers route the addresses of the grid that are on this site (see `index.php` of the helpers).
 */
final class Services
{
    /** The service and the script of the helpers that answers it. */
    public const SCRIPTS = [
        'query' => 'query.php',
        'register' => 'register.php',
        'offline' => 'offline.php',
        'currency' => 'currency.php',
        'guide' => 'guide.php',
        'motd' => 'motd.php',
        'landtool' => 'landtool.php',
        'parser' => 'parser.php',
        'eventsparser' => 'eventsparser.php',
        'textgen' => 'textgen.php',
        'directory_info' => 'directory_info.php',
    ];

    /** The pages of the helpers and their default path: the home page, the splash page of the viewer, the profiles, the health. */
    public const PAGES = ['home' => '/', 'welcome' => '/welcome', 'profile' => '/profile', 'health' => '/health'];

    /**
     * @param string $base the path the helpers are served under (`/helpers`), the one of the economy
     */
    public function __construct(private string $base = '/helpers') {}

    /** A path as the web server has it: a slash first, none at the end (the root is `/`). */
    public static function normalize(string $path): string
    {
        $path = '/' . trim(trim($path), '/');

        return $path;
    }

    /**
     * The path of an address a Robust setting gives, with its constants as they are written (`${Const|WebURL}/helpers`).
     */
    public static function pathOf(string $address): string
    {
        $address = (string) preg_replace('/^\$\{Const\|[^}]+\}/', '', trim($address));

        return self::normalize((string) (parse_url($address, PHP_URL_PATH) ?? $address));
    }

    /** The base path. */
    public function base(): string
    {
        return self::normalize($this->base);
    }

    /** The default path of a service or a page. */
    public function path(string $service): string
    {
        if (isset(self::PAGES[$service])) {
            return self::PAGES[$service];
        }

        return rtrim($this->base(), '/') . '/' . (self::SCRIPTS[$service] ?? $service);
    }

    /** The address of a service on a web site. */
    public function url(string $webUrl, string $service): string
    {
        return rtrim($webUrl, '/') . $this->path($service);
    }
}
