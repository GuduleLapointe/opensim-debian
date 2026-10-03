<?php

declare(strict_types=1);

namespace OpenSim\Installer\Web;

/**
 * What a web server needs to serve the helpers of a grid: the base path aliased to the helpers,
 * the folders that are not for the web denied, PHP run for the scripts, the grid named to the
 * helpers (OPENSIM_GRID), and, for a service the grid gives its own path, an alias to its script.
 *
 * Caddy, nginx and Apache are written; to include in the site of the grid, not to replace its config.
 */
final class Snippets
{
    public const SERVERS = ['caddy', 'nginx', 'apache'];

    public const WEBROOT = '/usr/share/opensim-helpers';
    public const SOCKET = '/run/php/php-fpm.sock';

    /** @throws \InvalidArgumentException for a server that is not written */
    public static function render(
        string $server,
        string $nick,
        Services $services,
        string $webroot = self::WEBROOT,
        string $socket = self::SOCKET,
    ): string {
        return match ($server) {
            'caddy' => self::caddy($nick, $services, $webroot, $socket),
            'nginx' => self::nginx($nick, $services, $webroot, $socket),
            'apache' => self::apache($nick, $services, $webroot),
            default => throw new \InvalidArgumentException("No snippet for $server (" . implode(', ', self::SERVERS) . ').'),
        };
    }

    private static function header(string $server, string $nick, Services $services, string $how): string
    {
        return "# Helpers of the grid $nick, written by `opensim web $nick snippet $server`.\n" .
            "# $how\n" .
            "# Helpers at {$services->base()}; a service with its own path (helpers.ini, [Urls]) has an alias below.\n";
    }

    private static function caddy(string $nick, Services $services, string $webroot, string $socket): string
    {
        $private = implode(' ', array_map(static fn(string $f): string => "/$f/*", Services::PRIVATE_FOLDERS));
        $base = $services->base();
        $out = self::header('caddy', $nick, $services, 'Put it in the site block of the web site, or save it and `import` it there.');
        $out .= "\nhandle " . ($base === '/' ? '/*' : "$base/*") . " {\n";
        $out .= "\troot * $webroot\n";
        if ($base !== '/') {
            $out .= "\turi strip_prefix $base\n";
        }
        $out .= "\t@private path $private\n\trespond @private 404\n";
        $out .= self::caddyPhp($nick, $socket);
        $out .= "\tfile_server\n}\n";
        foreach ($services->aliases() as $service => $path) {
            $out .= "\nhandle $path {\n\troot * $webroot\n\trewrite * /" . Services::SCRIPTS[$service] . "\n";
            $out .= self::caddyPhp($nick, $socket) . "}\n";
        }

        return $out;
    }

    private static function caddyPhp(string $nick, string $socket): string
    {
        return "\tphp_fastcgi unix/$socket {\n\t\tenv OPENSIM_GRID $nick\n\t}\n";
    }

    private static function nginx(string $nick, Services $services, string $webroot, string $socket): string
    {
        $private = implode('|', Services::PRIVATE_FOLDERS);
        $base = rtrim($services->base(), '/');
        $out = self::header('nginx', $nick, $services, 'Put it in the server block of the web site. Adjust the socket of PHP-FPM to your version.');
        $out .= "\nlocation $base/ {\n\talias $webroot/;\n\tindex index.php;\n\n";
        $out .= "\t# Not for the web (first: the first matching pattern wins)\n";
        $out .= "\tlocation ~ ^$base/($private)/ {\n\t\tdeny all;\n\t}\n\n";
        $out .= "\tlocation ~ \\.php\$ {\n\t\tinclude snippets/fastcgi-php.conf;\n\t\tfastcgi_param SCRIPT_FILENAME \$request_filename;\n";
        $out .= "\t\tfastcgi_param OPENSIM_GRID $nick;\n\t\tfastcgi_pass unix:$socket;\n\t}\n}\n";
        foreach ($services->aliases() as $service => $path) {
            $out .= "\nlocation = $path {\n\tinclude snippets/fastcgi-php.conf;\n";
            $out .= "\tfastcgi_param SCRIPT_FILENAME $webroot/" . Services::SCRIPTS[$service] . ";\n";
            $out .= "\tfastcgi_param OPENSIM_GRID $nick;\n\tfastcgi_pass unix:$socket;\n}\n";
        }

        return $out;
    }

    private static function apache(string $nick, Services $services, string $webroot): string
    {
        $private = implode('|', Services::PRIVATE_FOLDERS);
        $base = rtrim($services->base(), '/');
        $out = self::header('apache', $nick, $services, 'Put it in the virtual host of the web site (PHP through libapache2-mod-php or PHP-FPM handling .php).');
        $out .= "\nAlias " . ($base === '' ? '/' : $base) . " $webroot\n";
        $out .= "<Directory $webroot>\n\tOptions -Indexes\n\tRequire all granted\n\tDirectoryIndex index.php\n\tSetEnv OPENSIM_GRID $nick\n</Directory>\n";
        $out .= "\n# Not for the web\n<DirectoryMatch \"^$webroot/($private)/\">\n\tRequire all denied\n</DirectoryMatch>\n";
        foreach ($services->aliases() as $service => $path) {
            $out .= "\nAliasMatch ^" . preg_quote($path, '#') . "\$ $webroot/" . Services::SCRIPTS[$service] . "\n";
        }

        return $out;
    }
}
