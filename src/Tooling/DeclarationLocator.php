<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Finds the declaration files of the installed packages.
 *
 * A package announces its file in its composer.json, under extra.pollora.i18n, as a
 * path relative to the package. Composer copies that key into vendor/composer/installed.json,
 * which is read here as plain JSON: nothing of Composer runs.
 */
final class DeclarationLocator
{
    /**
     * @param  string  $installedJsonPath  Path of vendor/composer/installed.json
     * @return list<string> The declaration files that packages announce, in the order of the list
     *
     * @throws InvalidDeclarationException
     */
    public function locate(string $installedJsonPath): array
    {
        $json = is_file($installedJsonPath) ? @file_get_contents($installedJsonPath) : false;

        if ($json === false) {
            throw InvalidDeclarationException::installedNotReadable($installedJsonPath);
        }

        $installed = json_decode($json, true);

        if (! is_array($installed)) {
            throw InvalidDeclarationException::installedMalformed($installedJsonPath);
        }

        // Composer 2 wraps the packages in {"packages": [...]}, Composer 1 wrote the list itself.
        $packages = isset($installed['packages']) && is_array($installed['packages']) ? $installed['packages'] : $installed;
        $directory = dirname($installedJsonPath);
        $files = [];

        foreach ($packages as $package) {
            $relative = is_array($package) && is_array($package['extra'] ?? null) && is_array($package['extra']['pollora'] ?? null)
                ? ($package['extra']['pollora']['i18n'] ?? null)
                : null;

            if ($relative === null) {
                continue;
            }

            $name = is_string($package['name'] ?? null) ? $package['name'] : 'a package';

            if (! is_string($relative) || ! $this->isRelative($relative)) {
                throw InvalidDeclarationException::invalidPackagePath($installedJsonPath, $name);
            }

            $installPath = is_string($package['install-path'] ?? null) ? $package['install-path'] : '../'.$name;
            $files[] = $this->normalize($directory.'/'.$installPath.'/'.$relative);
        }

        return $files;
    }

    private function isRelative(string $path): bool
    {
        return $path !== '' && $path[0] !== '/' && ! in_array('..', explode('/', str_replace('\\', '/', $path)), true);
    }

    private function normalize(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && ($segment !== '' || $parts === [])) {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }
}
