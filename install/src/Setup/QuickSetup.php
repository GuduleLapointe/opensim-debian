<?php

declare(strict_types=1);

namespace OpenSim\Installer\Setup;

use OpenSim\Installer\Grid\GridAccounts;
use OpenSim\Installer\Ui\InstallerUi;

/**
 * The quick setup: one form with what a grid needs from its owner (its name, the owner's account, the
 * database), then the setup runs with the defaults for everything else, showing the plan to accept (the
 * advanced setup, with every question, is one answer away).
 */
final class QuickSetup
{
    public function __construct(private InstallerUi $ui) {}

    /**
     * What the form gives, as a setup (see SetupFile): a grid, its owner, one simulator with its first region.
     *
     * @return array<string,mixed>
     */
    public function ask(): array
    {
        $this->ui->note(
            _("A grid and its first region, with the usual settings. You will see them before anything is written, and can go to the advanced setup instead."),
        );
        $v = $this->ui->form([
            ['key' => 'name', 'label' => _('Grid name'), 'default' => '', 'hint' => _('As the viewers show it')],
            [
                'key' => 'owner',
                'label' => _('Grid owner (First Last)'),
                'default' => '',
                'hint' => _('The first account, owner of the first estate'),
                'validate' => static fn(string $v): ?string => GridAccounts::validName(trim($v))
                    ? null
                    : _('First and last name, e.g. Jane Doe.'),
            ],
            [
                'key' => 'password',
                'label' => _('Owner password'),
                'type' => 'secret',
                'validate' => static fn(string $v): ?string => preg_match('/^[^"\r\n]{6,}$/', $v)
                    ? null
                    : _('At least 6 characters, without double quote.'),
            ],
            [
                'key' => 'email',
                'label' => _('Owner email (optional)'),
                'required' => false,
                'validate' => static fn(string $v): ?string => preg_match('/^[^\s"\'\\\\]+@[^\s"\'\\\\]+$/', trim($v))
                    ? null
                    : _('An email address, or nothing.'),
            ],
            ['key' => 'db_user', 'label' => _('Database user'), 'default' => 'opensim'],
            ['key' => 'db_password', 'label' => _('Database password'), 'type' => 'secret'],
        ]);

        $owner = ['name' => $v['owner'], 'password' => $v['password']];
        if ($v['email'] !== '') {
            $owner['email'] = $v['email'];
        }

        return [
            'grid' => ['name' => $v['name'], 'database' => ['user' => $v['db_user'], 'password' => $v['db_password']]],
            'owner' => $owner,
            // The first simulator and its region: the defaults of the setup
            'simulators' => [[]],
        ];
    }
}
