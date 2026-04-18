<?php

declare(strict_types=1);

// Path references updated for the Gutenberg adoption (#309). The legacy editor
// entry now lives under _legacy/; M3 replaces mount.blade.php with the new
// Blade component and these tests will be rewritten alongside it.
$viewPath = __DIR__.'/../../resources/views/editor/mount.blade.php';
$routesPath = __DIR__.'/../../routes/web.php';
$bootPath = __DIR__.'/../../resources/js/visual-editor/_legacy/editor/main.tsx';

test('mount blade view exists', function () use ($viewPath) {
    expect(file_exists($viewPath))->toBeTrue();
});

test('mount blade view renders the ve-root element with data attributes', function () use ($viewPath) {
    $contents = file_get_contents($viewPath);

    expect($contents)->toContain('id="ve-root"');
    expect($contents)->toContain('data-post-id="{{ $postId }}"');
    expect($contents)->toContain('data-post-type="{{ $postType }}"');
    expect($contents)->toContain('data-api-base="{{ $apiBase }}"');
});

test('mount blade view pulls the editor entry through @vite', function () use ($viewPath) {
    $contents = file_get_contents($viewPath);

    expect($contents)->toContain("@vite(['resources/js/visual-editor/_legacy/editor/main.tsx'])");
});

test('package routes register the /editor path', function () use ($routesPath) {
    $contents = file_get_contents($routesPath);

    expect($contents)->toContain("Route::get('/editor'");
    expect($contents)->toContain('visual-editor::editor.mount');
    expect($contents)->toContain("'postId'");
    expect($contents)->toContain("'postType'");
    expect($contents)->toContain("'apiBase'");
    expect($contents)->not->toContain('/editor-spike');
});

test('boot script targets #ve-root and logs a clear error when missing', function () use ($bootPath) {
    $contents = file_get_contents($bootPath);

    expect($contents)->toContain("'ve-root'");
    expect($contents)->toContain('console.error');
    expect($contents)->toContain('data-post-id');
    expect($contents)->toContain('data-post-type');
    expect($contents)->toContain('data-api-base');
});
