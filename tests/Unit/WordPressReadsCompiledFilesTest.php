<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\L10nPhpWriter;
use Pollora\I18n\Tooling\MoCompiler;

/**
 * Where WordPress is installed: POLLORA_WORDPRESS_PATH, or the public/cms folder of a Pollora project in DDEV.
 */
function wordPressPath(): ?string
{
    foreach ([getenv('POLLORA_WORDPRESS_PATH'), '/var/www/html/public/cms'] as $path) {
        if (is_string($path) && is_file($path.'/wp-includes/pomo/mo.php')) {
            return $path;
        }
    }

    return null;
}

/**
 * @return array{headers: array<string, string>, messages: array<array-key, list<string>>, po: string}
 */
function wordPressSample(): array
{
    return require __DIR__.'/../Fixtures/sample.php';
}

beforeEach(function (): void {
    $path = wordPressPath();

    if ($path === null) {
        $this->markTestSkipped('WordPress is not installed here (set POLLORA_WORDPRESS_PATH).');
    }

    foreach (['pomo/entry', 'pomo/translations', 'pomo/streams', 'pomo/plural-forms', 'pomo/mo'] as $file) {
        require_once $path.'/wp-includes/'.$file.'.php';
    }

    foreach (['class-wp-translation-file', 'class-wp-translation-file-mo', 'class-wp-translation-file-php'] as $file) {
        require_once $path.'/wp-includes/l10n/'.$file.'.php';
    }

    $this->directory = sys_get_temp_dir().'/pollora-i18n-wp-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    if (isset($this->directory)) {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }
});

it('loads the .mo file with the MO class of WordPress', function (): void {
    $sample = wordPressSample();
    file_put_contents($this->directory.'/fr_FR.mo', (new MoCompiler)->compile($sample['headers'], $sample['messages']));

    $mo = new MO;

    expect($mo->import_from_file($this->directory.'/fr_FR.mo'))->toBeTrue()
        ->and($mo->translate('Book'))->toBe('Livre')
        ->and($mo->translate('Book', 'post type'))->toBe('Ouvrage')
        ->and($mo->translate_plural('%d book', '%d books', 1))->toBe('%d livre')
        ->and($mo->translate_plural('%d book', '%d books', 2))->toBe('%d livres')
        ->and($mo->translate_plural('File', 'Files', 2, 'menu'))->toBe('Fichiers')
        ->and($mo->translate('Café ☕'))->toBe('Café ☕ — déjà vu, 日本語')
        ->and($mo->translate("Tab\there\nline"))->toBe("Tab\tici\nligne")
        ->and($mo->translate('Quote "double" and \'single\' and back\\slash and $dollar and {$brace}'))->toBe('Guillemets "doubles", \'simples\', \\ et $dollar et {$accolade}')
        ->and($mo->translate('42'))->toBe('quarante-deux')
        ->and($mo->translate('Untranslated'))->toBe('Untranslated')
        ->and($mo->get_header('Language'))->toBe('fr_FR')
        ->and($mo->get_header('Plural-Forms'))->toBe('nplurals=2; plural=(n > 1);');
});

it('loads the .mo and the .l10n.php files with WP_Translation_File and gets the same translations', function (): void {
    $sample = wordPressSample();
    file_put_contents($this->directory.'/fr_FR.mo', (new MoCompiler)->compile($sample['headers'], $sample['messages']));
    file_put_contents($this->directory.'/fr_FR.l10n.php', (new L10nPhpWriter)->render($sample['headers'], $sample['messages']));

    $fromMo = WP_Translation_File::create($this->directory.'/fr_FR.mo');
    $fromPhp = WP_Translation_File::create($this->directory.'/fr_FR.l10n.php', 'php');

    expect($fromMo)->toBeInstanceOf(WP_Translation_File_MO::class)
        ->and($fromPhp)->toBeInstanceOf(WP_Translation_File_PHP::class)
        ->and($fromMo->error())->toBeNull()
        ->and($fromPhp->error())->toBeNull();

    expect($fromPhp->entries())->toEqual($fromMo->entries())
        ->and($fromPhp->headers()['plural-forms'])->toBe('nplurals=2; plural=(n > 1);')
        ->and($fromPhp->headers()['language'])->toBe('fr_FR')
        ->and($fromPhp->get_plural_form(1))->toBe(0)
        ->and($fromPhp->get_plural_form(2))->toBe(1)
        ->and($fromPhp->translate('Book'))->toBe('Livre')
        ->and($fromPhp->translate("post type\x04Book"))->toBe('Ouvrage')
        ->and($fromPhp->translate('%d book'))->toBe("%d livre\0%d livres")
        ->and($fromPhp->translate("menu\x04File"))->toBe("Fichier\0Fichiers")
        ->and($fromPhp->translate('Café ☕'))->toBe('Café ☕ — déjà vu, 日本語')
        ->and($fromPhp->translate('Quote "double" and \'single\' and back\\slash and $dollar and {$brace}'))->toBe('Guillemets "doubles", \'simples\', \\ et $dollar et {$accolade}')
        ->and($fromPhp->translate('Untranslated'))->toBeFalse();
});

it('resolves a language with three plural forms', function (): void {
    $headers = ['Language' => 'ru_RU', 'Plural-Forms' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);'];
    $messages = ["%d file\0%d files" => ['%d файл', '%d файла', '%d файлов']];

    file_put_contents($this->directory.'/ru_RU.mo', (new MoCompiler)->compile($headers, $messages));
    file_put_contents($this->directory.'/ru_RU.l10n.php', (new L10nPhpWriter)->render($headers, $messages));

    foreach ([$this->directory.'/ru_RU.mo', $this->directory.'/ru_RU.l10n.php'] as $file) {
        $translations = WP_Translation_File::create($file);
        $forms = explode("\0", (string) $translations->translate('%d file'));

        expect($forms)->toBe(['%d файл', '%d файла', '%d файлов'])
            ->and($forms[$translations->get_plural_form(1)])->toBe('%d файл')
            ->and($forms[$translations->get_plural_form(2)])->toBe('%d файла')
            ->and($forms[$translations->get_plural_form(5)])->toBe('%d файлов');
    }

    $mo = new MO;
    $mo->import_from_file($this->directory.'/ru_RU.mo');

    expect($mo->translate_plural('%d file', '%d files', 21))->toBe('%d файл')
        ->and($mo->translate_plural('%d file', '%d files', 3))->toBe('%d файла')
        ->and($mo->translate_plural('%d file', '%d files', 11))->toBe('%d файлов');
});
