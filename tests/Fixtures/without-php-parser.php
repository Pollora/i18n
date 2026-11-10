<?php

declare(strict_types=1);

/*
 * Loaded before a script (auto_prepend_file): Composer's autoloader stays, but it no longer
 * finds anything under PhpParser\, as in a project that did not install nikic/php-parser.
 */

use Composer\Autoload\ClassLoader;

$loader = require dirname(__DIR__, 2).'/vendor/autoload.php';

if ($loader instanceof ClassLoader) {
    $loader->unregister();
    spl_autoload_register(static function (string $class) use ($loader): void {
        if (! str_starts_with($class, 'PhpParser\\')) {
            $loader->loadClass($class);
        }
    });
}
