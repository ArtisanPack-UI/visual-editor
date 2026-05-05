<?php

declare(strict_types=1);

use ArtisanPackUI\VisualEditor\SiteEditor\CmsFrameworkIntegration;
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
//
// `web` middleware is applied explicitly: `loadRoutesFrom()` does not
// attach it by default, and without a session on the SPA page load the
// `csrf_token()` helper in the blade returns a token that isn't tied to
// any session cookie — which makes the first REST mutation (CREATE,
// PUT) fail with "CSRF token mismatch" (D2 #369).
//
// H7 (#432). The closure short-circuits to an install-gate view when
// cms-framework's SiteEditor module is not booted. Without the gate the
// SPA would mount and then receive a cascade of 404s from every H6
// controller — a bad first-run experience that the gate replaces with a
// single `composer require artisanpack-ui/cms-framework` instruction.
Route::middleware('web')
    ->group(function (): void {
        Route::get('/visual-editor/site/{path?}', function () {
            if (! CmsFrameworkIntegration::isAvailable()) {
                return response()->view('visual-editor::site-editor.install-gate', [
                    'postEditorUrl' => route('visual-editor.editor'),
                ]);
            }

            return view('visual-editor::site-editor.index');
        })
            ->where('path', '.*')
            ->name('visual-editor.site-editor');
    });
