<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/editor-spike', function () {
    return view('visual-editor::editor-spike');
})->name('visual-editor.editor-spike');
