<?php

declare(strict_types=1);

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
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
// H7 (#432). Access is decided by the bound `SiteEditorAccessGate`.
// The package default fails closed (see `DenyByDefaultGate`); consumers
// override the binding with `CmsFrameworkInstallGate` or their own
// implementation that composes role / auth checks with the install
// probe. See `docs/site-editor-access-gate.md`.
Route::middleware('web')
    ->group(function (): void {
        Route::get('/visual-editor/site/{path?}', function (Request $request, SiteEditorAccessGate $gate) {
            if ($denial = $gate->check($request)) {
                return $denial;
            }

            return view('visual-editor::site-editor.index');
        })
            ->where('path', '.*')
            ->name('visual-editor.site-editor');
    });
