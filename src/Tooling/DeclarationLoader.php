<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use Pollora\I18n\Runtime\TranslatableRegistry;
use Throwable;

/**
 * Feeds the registry from declaration files: plain data files returning
 * [Class::class => ['field', ...]], shipped by the package that defines the attributes.
 */
final class DeclarationLoader
{
    public function __construct(private readonly DeclarationLocator $locator = new DeclarationLocator) {}

    /**
     * Declares the fields of one file. The file is checked as a whole first, so a malformed file declares nothing.
     *
     * @return int The number of classes the file declares
     *
     * @throws InvalidDeclarationException
     */
    public function loadFile(string $path): int
    {
        $declarations = $this->read($path);

        foreach ($declarations as $class => $fields) {
            TranslatableRegistry::register($class, $fields);
        }

        return count($declarations);
    }

    /**
     * @param  list<string>  $paths
     * @return int The number of classes declared
     *
     * @throws InvalidDeclarationException
     */
    public function loadFiles(array $paths): int
    {
        $count = 0;

        foreach ($paths as $path) {
            $count += $this->loadFile($path);
        }

        return $count;
    }

    /**
     * Declares what the installed packages announce.
     *
     * @param  string  $installedJsonPath  Path of vendor/composer/installed.json
     * @return int The number of classes declared
     *
     * @throws InvalidDeclarationException
     */
    public function loadInstalled(string $installedJsonPath): int
    {
        return $this->loadFiles($this->locator->locate($installedJsonPath));
    }

    /**
     * @return array<class-string, list<string>>
     */
    private function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw InvalidDeclarationException::fileNotFound($path);
        }

        try {
            $declarations = (static fn (): mixed => require $path)();
        } catch (Throwable $throwable) {
            throw InvalidDeclarationException::fileNotLoadable($path, $throwable);
        }

        if (! is_array($declarations)) {
            throw InvalidDeclarationException::notAnArray($path);
        }

        foreach ($declarations as $class => $fields) {
            if (! is_string($class) || $class === '') {
                throw InvalidDeclarationException::invalidClass($path, $class);
            }

            if (! is_array($fields) || ! array_is_list($fields) || $fields === [] || array_filter($fields, static fn (mixed $field): bool => ! is_string($field) || $field === '') !== []) {
                throw InvalidDeclarationException::invalidFields($path, $class);
            }
        }

        /** @var array<class-string, list<string>> $checked */
        $checked = $declarations;

        return $checked;
    }
}
