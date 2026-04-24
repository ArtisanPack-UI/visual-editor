<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/editor', function () {
    return view('visual-editor::editor.mount', [
        'postId' => '1',
        'postType' => 'page',
        'apiBase' => '/api',
    ]);
})->name('visual-editor.editor');

// Temporary sandbox route for M1 (#311). Mounts an empty BlockEditorProvider
// to prove @wordpress/* packages import cleanly and the Gutenberg canvas
// renders. Removed once the real editor shell ships (M3+).
Route::get('/ve-sandbox', function () {
    return view('visual-editor::sandbox.index');
})->name('visual-editor.sandbox');

// D1 (#368). Site-editor shell. A single catch-all entry mounts the SPA and
// hands routing inside the shell to the React app via `history.pushState`.
// Sub-paths the SPA recognises: `templates`, `template-parts`, `patterns`,
// `styles`, `navigation` (each optionally followed by an entity id).
Route::get('/visual-editor/site/{path?}', function () {
    return view('visual-editor::site-editor.index');
})
    ->where('path', '.*')
    ->name('visual-editor.site-editor');
