<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

/*
 * Automated refactoring and upgrade checks. CI runs it with --dry-run, so the code must stay clean:
 * `composer rector` applies the changes locally.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/config', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withPhpSets()
    // No typeDeclarations set: it would add `: mixed` to every short Filament closure.
    ->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // app() is the idiom used across the codebase; resolve() would only rename it.
        RectorLaravel\Rector\FuncCall\AppToResolveRector::class,
        // Project rule: local today is Period::localToday(); today() would hide the UTC trap.
        RectorLaravel\Rector\FuncCall\NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        // It turns lazy closures into eager first-class callables: `fn () => make()->run()` would build
        // the object at once, e.g. inside a data provider before the database exists.
        Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector::class,
        // Filament helpers are static factories by design.
        Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector::class,
    ]);
