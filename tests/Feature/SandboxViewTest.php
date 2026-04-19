<?php

declare(strict_types=1);

// M1 sandbox wiring tests (#311). The /ve-sandbox route mounts an empty
// BlockEditorProvider to prove @wordpress/* packages import cleanly and
// lazy-load. Removed when the real editor shell ships (M3+).

$repoRoot = __DIR__.'/../..';
$viewPath = $repoRoot.'/resources/views/sandbox/index.blade.php';
$routesPath = $repoRoot.'/routes/web.php';
$mainEntryPath = $repoRoot.'/resources/js/visual-editor/sandbox/main.tsx';
$sandboxEditorPath = $repoRoot.'/resources/js/visual-editor/sandbox/sandbox-editor.tsx';
$packageJsonPath = $repoRoot.'/package.json';
$viteConfigPath = $repoRoot.'/vite.config.ts';
$renovateConfigPath = $repoRoot.'/.github/renovate.json';

test('sandbox blade view exists and renders the ve-sandbox-root element', function () use ($viewPath) {
    expect(file_exists($viewPath))->toBeTrue();

    $contents = file_get_contents($viewPath);

    expect($contents)->toContain('id="ve-sandbox-root"');
    expect($contents)->toContain("@vite(['resources/js/visual-editor/sandbox/main.tsx'])");
});

test('package routes register the /ve-sandbox path', function () use ($routesPath) {
    $contents = file_get_contents($routesPath);

    expect($contents)->toContain("Route::get('/ve-sandbox'");
    expect($contents)->toContain('visual-editor::sandbox.index');
    expect($contents)->toContain("->name('visual-editor.sandbox')");
});

test('sandbox main entry targets #ve-sandbox-root and dynamic-imports the gutenberg module', function () use ($mainEntryPath) {
    $contents = file_get_contents($mainEntryPath);

    expect($contents)->toContain("'ve-sandbox-root'");
    expect($contents)->toContain('console.error');
    expect($contents)->toContain("await import('./sandbox-editor')");
});

test('sandbox editor component mounts BlockEditorProvider against a hardcoded block tree', function () use ($sandboxEditorPath) {
    $contents = file_get_contents($sandboxEditorPath);

    expect($contents)->toContain("from '@wordpress/blocks'");
    expect($contents)->toContain("from '@wordpress/block-editor'");
    expect($contents)->toContain("from '@wordpress/block-library'");
    expect($contents)->toContain("from '@wordpress/components'");
    expect($contents)->toContain("from '@wordpress/i18n'");
    expect($contents)->toContain('BlockEditorProvider');
    expect($contents)->toContain('registerCoreBlocks()');
    expect($contents)->toContain('createBlock(');
});

test('package.json pins the @wordpress/* dependencies exactly', function () use ($packageJsonPath) {
    $packageJson = json_decode(file_get_contents($packageJsonPath), true);

    expect($packageJson)->toBeArray();

    $required = [
        '@wordpress/blocks',
        '@wordpress/block-editor',
        '@wordpress/block-library',
        '@wordpress/components',
        '@wordpress/i18n',
    ];

    foreach ($required as $pkg) {
        expect($packageJson['dependencies'])->toHaveKey($pkg);

        $version = $packageJson['dependencies'][$pkg];
        expect($version)
            ->not->toStartWith('^')
            ->not->toStartWith('~')
            ->toMatch('/^\d+\.\d+\.\d+/', "{$pkg} must be exact-pinned, got: {$version}");
    }
});

test('package.json pins react and react-dom exactly to match Gutenberg peers', function () use ($packageJsonPath) {
    $packageJson = json_decode(file_get_contents($packageJsonPath), true);

    foreach (['react', 'react-dom'] as $pkg) {
        $version = $packageJson['dependencies'][$pkg];
        expect($version)
            ->not->toStartWith('^')
            ->not->toStartWith('~')
            ->toStartWith('18.');
    }
});

test('vite config emits a gutenberg chunk and registers the sandbox entry', function () use ($viteConfigPath) {
    $contents = file_get_contents($viteConfigPath);

    expect($contents)->toContain('sandbox:');
    expect($contents)->toContain('manualChunks');
    expect($contents)->toContain("'/node_modules/@wordpress/'");
    expect($contents)->toContain("return 'gutenberg'");
});

test('renovate config groups @wordpress/* updates on a two-week cadence', function () use ($renovateConfigPath) {
    expect(file_exists($renovateConfigPath))->toBeTrue();

    $config = json_decode(file_get_contents($renovateConfigPath), true);

    expect($config)->toBeArray();
    expect($config)->toHaveKey('packageRules');

    $gutenbergRule = null;
    foreach ($config['packageRules'] as $rule) {
        if (($rule['groupName'] ?? null) === 'gutenberg') {
            $gutenbergRule = $rule;
            break;
        }
    }

    expect($gutenbergRule)->not->toBeNull();
    expect($gutenbergRule['matchPackagePrefixes'] ?? [])->toContain('@wordpress/');
    expect($gutenbergRule['schedule'] ?? [])->toContain('every 2 weeks on monday');
    expect($gutenbergRule['rangeStrategy'] ?? null)->toBe('pin');
});
