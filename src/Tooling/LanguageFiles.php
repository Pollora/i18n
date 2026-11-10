<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Finds the translation files of a folder by the names WordPress expects: "{domain}-{locale}.po".
 */
final class LanguageFiles
{
    public const string LOCALE_PATTERN = '/^[a-z]{2,3}(?:_[A-Za-z0-9]+)*$/';

    /**
     * @param  string|null  $domain  Only the files of this text domain; every .po file when null
     * @return array<string, string> Path by locale for a domain; path by "domain-locale" without one; sorted by name
     */
    public function poFiles(string $directory, ?string $domain = null): array
    {
        $names = is_dir($directory) ? (scandir($directory) ?: []) : [];
        sort($names, SORT_STRING);
        $files = [];
        $prefix = $domain === null ? '' : $domain.'-';

        foreach ($names as $name) {
            if (! str_ends_with($name, '.po') || ! str_starts_with($name, $prefix) || strlen($name) <= strlen($prefix) + 3) {
                continue;
            }

            $locale = substr($name, strlen($prefix), -3);

            if ($domain === null || preg_match(self::LOCALE_PATTERN, $locale) === 1) {
                $files[$locale] = $this->path($directory, $domain, $locale, '.po');
            }
        }

        return $files;
    }

    public function path(string $directory, ?string $domain, string $locale, string $extension): string
    {
        return rtrim($directory, '/').'/'.($domain === null ? '' : $domain.'-').$locale.$extension;
    }
}
