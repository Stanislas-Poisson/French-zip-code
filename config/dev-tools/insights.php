<?php

declare(strict_types=1);

/*
 * PHP Insights Configuration
 * --------------------------.
 *
 *  This file extends the base configuration from laravel-dev-tools.
 *
 *  IMPORTANT: For arrays with numeric keys (remove, add, exclude),
 *  use the spread operator [...] to merge. Do NOT use array_replace_recursive()
 *  as it replaces by index instead of merging.
 */

use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenDefineFunctions;
use SlevomatCodingStandard\Sniffs\Classes\ForbiddenPublicPropertySniff;
use SlevomatCodingStandard\Sniffs\TypeHints\ReturnTypeHintSniff;

/* Load base configuration from package */
$baseConfig = [];

/* Prevent infinite recursion */
$currentFile   = __FILE__;
$possiblePaths = [
    /* Normal Laravel project */
    dirname(__DIR__, 2) . '/vendor/zairakai/laravel-dev-tools/config/insights.base.php',

    /* Testbench environment */
    dirname(__DIR__, 6) . '/config/insights.base.php',

    /* Local project config */
    dirname(__DIR__, 2) . '/config/insights.base.php',
];

foreach ($possiblePaths as $path) {
    if (
        file_exists($path)
        && realpath($path) !== realpath($currentFile)
    ) {
        $baseConfig = require $path;

        break;
    }
}

/*
 *  Custom Configuration
 * ---------------------
 *
 * Add your package-specific rules here.
 * Use spread operator for numeric arrays (remove, add, exclude).
 *
 * Example:
 * $baseConfig['config'][CyclomaticComplexityIsHigh::class] = [
 *     ...($baseConfig['config'][CyclomaticComplexityIsHigh::class] ?? []),
 *     'exclude' => [
 *         ...($baseConfig['config'][CyclomaticComplexityIsHigh::class]['exclude'] ?? []),
 *         'Console/Commands/Dev/PublishToolingCommand.php',
 *     ],
 * ];
 */

// Eloquent and the queue read these properties publicly ($timestamps, $timeout, $tries, promoted job arguments).
$baseConfig['config'][ForbiddenPublicPropertySniff::class] = [
    'exclude' => [
        'app/Models',
        'app/Jobs',
    ],
];

// Eloquent relations are typed with generics (BelongsTo<Commune, $this>) that the sniff cannot read.
$baseConfig['config'][ReturnTypeHintSniff::class] = [
    ...($baseConfig['config'][ReturnTypeHintSniff::class] ?? []),
    'exclude' => [
        'app/Models',
    ],
];

// PHPInsights does not read the methods of an enum and takes them for global functions.
$baseConfig['config'][ForbiddenDefineFunctions::class] = [
    'exclude' => [
        'app/Enums',
    ],
];

// The PHPStan result cache (build/) is made of PHP files that must not be analysed.
$baseConfig['exclude'] = [
    ...($baseConfig['exclude'] ?? []),
    'build',
];

return $baseConfig;
