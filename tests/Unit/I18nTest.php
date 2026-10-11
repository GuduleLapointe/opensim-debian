<?php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use OpenSimKit\I18n\I18n;

test('translates through a compiled catalogue', function () {
    if (!shell_exec('command -v msgfmt') || !preg_match('/fr_FR\.utf-?8/i', (string) shell_exec('locale -a'))) {
        test()->markTestSkipped('msgfmt or the fr_FR locale is missing');
    }
    $dir = test_tmp() . '/i18n-' . uniqid();
    mkdir("$dir/fr_FR/LC_MESSAGES", 0777, true);
    file_put_contents(
        "$dir/fr.po",
        "msgid \"\"\nmsgstr \"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Grid name\"\nmsgstr \"Nom de la grille\"\n",
    );
    shell_exec(
        'msgfmt -o ' . escapeshellarg("$dir/fr_FR/LC_MESSAGES/opensim-kit.mo") . ' ' . escapeshellarg("$dir/fr.po"),
    );
    setlocale(LC_MESSAGES, 'fr_FR');
    expect(_('Grid name'))->toBe('Nom de la grille');
})->skip('No localization yet');
