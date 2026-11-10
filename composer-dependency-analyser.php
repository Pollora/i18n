<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/*
 * ARCH-07 for pollora/i18n.
 *
 * The tooling reads PHP with nikic/php-parser, a suggested package that the runtime never loads.
 * The runtime calls three WordPress functions only when they exist, so the
 * analyser cannot resolve them. The tests declare their own fixtures and use
 * the Pest functions, which are not Composer symbols.
 */
$root = getcwd() ?: '.';

return (new Configuration)
    ->disableReportingUnmatchedIgnores()
    ->ignoreUnknownFunctions(['__', 'get_locale', 'is_textdomain_loaded'])
    ->ignoreErrorsOnPackage('nikic/php-parser', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['pestphp/pest', 'pestphp/pest-plugin-arch'], [ErrorType::DEV_DEPENDENCY_IN_PROD, ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPath($root.'/tests', [ErrorType::UNKNOWN_CLASS, ErrorType::UNKNOWN_FUNCTION, ErrorType::SHADOW_DEPENDENCY]);
