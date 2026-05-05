<?php

declare(strict_types=1);

// D1 (#368). Site-editor shell wiring tests. The new /visual-editor/site
// route mounts the SPA defined in resources/js/visual-editor/site-editor;
// these checks confirm the static plumbing (blade view, route, vite
// entry, mount attributes) is in place so the JS-side tests can assert
// the SPA itself.

$repoRoot = __DIR__.'/../..';
$viewPath = $repoRoot.'/resources/views/site-editor/index.blade.php';
$routesPath = $repoRoot.'/routes/web.php';
$viteConfigPath = $repoRoot.'/vite.config.ts';
$mainEntryPath = $repoRoot.'/resources/js/visual-editor/site-editor/main.tsx';
$appPath = $repoRoot.'/resources/js/visual-editor/site-editor/site-editor-app.tsx';
$sectionsPath = $repoRoot.'/resources/js/visual-editor/site-editor/sections.tsx';
$navigatorPath = $repoRoot.'/resources/js/visual-editor/site-editor/navigator-sidebar.tsx';
$canvasPath = $repoRoot.'/resources/js/visual-editor/site-editor/canvas-frame.tsx';

test('site-editor blade view exists and exposes the SPA mount data attributes', function () use ($viewPath) {
    expect(file_exists($viewPath))->toBeTrue();

    $contents = file_get_contents($viewPath);

    expect($contents)->toContain('data-ap-site-editor');
    expect($contents)->toContain('data-route-base="/visual-editor/site"');
    expect($contents)->toContain("data-post-editor-url=\"{{ route('visual-editor.editor') }}\"");
    expect($contents)->toContain("@vite(['resources/js/visual-editor/site-editor/main.tsx'])");
});

test('package routes register the /visual-editor/site catch-all path', function () use ($routesPath) {
    $contents = file_get_contents($routesPath);

    expect($contents)->toContain("Route::get('/visual-editor/site/{path?}'");
    expect($contents)->toContain('visual-editor::site-editor.index');
    expect($contents)->toContain("->where('path', '.*')");
    expect($contents)->toContain("->name('visual-editor.site-editor')");
});

test('vite config registers the site-editor entry alongside the post-editor entry', function () use ($viteConfigPath) {
    $contents = file_get_contents($viteConfigPath);

    expect($contents)->toContain("'site-editor': siteEditorEntry");
    expect($contents)->toContain('site-editor/main.tsx');
});

test('site-editor main entry boots the SPA and reads the mount data attributes', function () use ($mainEntryPath) {
    $contents = file_get_contents($mainEntryPath);

    expect($contents)->toContain('data-ap-site-editor');
    expect($contents)->toContain('routeBase');
    expect($contents)->toContain('postEditorUrl');
    expect($contents)->toContain("import('./site-editor-app')");
});

test('site-editor app composes the four shell regions', function () use ($appPath) {
    $contents = file_get_contents($appPath);

    expect($contents)->toContain('NavigatorSidebar');
    expect($contents)->toContain('CanvasFrame');
    expect($contents)->toContain('InspectorOutlet');
    // Reuses the post-editor TopBar — must not fork it.
    expect($contents)->toContain("from '../editor/top-bar'");
    expect($contents)->toContain('inserterToggleAriaLabel');
    expect($contents)->toContain('inspectorToggleAriaLabel');
});

// H7 (#432). The styles, navigation, and patterns sections are loaded
// via React.lazy so their chunks stay out of the initial site-editor
// boot bundle. This test pins the lazy boundary at the source level —
// a regression that statically imports any of those modules would
// fold the chunk back into the main bundle.
test('site-editor app lazy-loads the three heavy section orchestrators', function () use ($appPath) {
    $contents = file_get_contents($appPath);

    // Suspense boundary + the dynamic-import targets must all be
    // present. Match on the import path alone, not the full
    // `lazy(() => import(...))` literal — the navigation entry wraps
    // across multiple lines because of its longer module path.
    expect($contents)->toContain('Suspense');
    expect($contents)->toContain("import('./styles/styles-section')");
    expect($contents)->toContain("import('./navigation/navigation-section')");
    expect($contents)->toContain("import('./patterns/patterns-section')");
    expect($contents)->toMatch('/lazy\(\s*\(\)\s*=>\s*import\(/');
    // The hooks must NOT be statically imported in the shell — the
    // lazy default exports own their hook calls now.
    expect($contents)->not->toContain('useStylesSectionViews');
    expect($contents)->not->toContain('useNavigationSectionViews');
    expect($contents)->not->toContain('usePatternsSectionViews');
});

test('section registry declares the five V1 site-editor sections', function () use ($sectionsPath) {
    $contents = file_get_contents($sectionsPath);

    foreach (['templates', 'template-parts', 'patterns', 'styles', 'navigation'] as $slug) {
        expect($contents)->toContain("id: '{$slug}'");
    }

    // Save labels per design brief §4.3 must name their scope.
    expect($contents)->toContain('Save template');
    expect($contents)->toContain('Save template part');
    expect($contents)->toContain('Save pattern');
    expect($contents)->toContain('Save global styles');
    expect($contents)->toContain('Save menu');
});

test('navigator sidebar exposes a navigation landmark and tablist semantics', function () use ($navigatorPath) {
    $contents = file_get_contents($navigatorPath);

    expect($contents)->toContain("aria-label={__('Site editor sections'");
    expect($contents)->toContain("role=\"tablist\"");
    expect($contents)->toContain("role=\"tab\"");
    expect($contents)->toContain('aria-orientation="vertical"');
});

test('canvas frame wraps BlockCanvas in a BlockEditorProvider', function () use ($canvasPath) {
    $contents = file_get_contents($canvasPath);

    expect($contents)->toContain('BlockEditorProvider');
    expect($contents)->toContain('BlockCanvas');
    // The empty state must be present so D1 doesn't ship a blank canvas.
    expect($contents)->toContain('ap-site-editor-canvas-empty');
});
