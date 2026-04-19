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
