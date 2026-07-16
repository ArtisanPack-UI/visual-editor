<?php

declare(strict_types=1);

use ArtisanPackUI\VisualEditor\Http\Requests\Icon\UploadIconSetRequest;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Package-level demo / fallback post-editor route. Host apps render the
// editor via `<x-visual-editor :model="$post" />` against their own models.
// This route exists so `route('visual-editor.editor')` resolves — it is
// surfaced as the site-editor exit link (#446) and in the install-gate
// copy (`CmsFrameworkInstallGate`). The mounted page bootstraps the
// editor against placeholder data attributes; without a backing host
// resource the editor will surface API errors, which is acceptable for
// the fallback / sample URL.
Route::get('/editor', function () {
    $resources = (array) config('artisanpack.visual-editor.resources', []);
    $contentTypes = [];

    foreach ($resources as $plural => $modelClass) {
        if (! is_string($plural) || $plural === '') {
            continue;
        }

        $singular = Str::singular($plural);

        $contentTypes[] = [
            'slug'   => $singular,
            'plural' => $plural,
            'label'  => ucwords(str_replace(['-', '_'], ' ', $singular)),
        ];
    }

    return view('visual-editor::editor.mount', [
        'resource' => 'pages',
        'modelId' => '1',
        'apiBase' => '/visual-editor/api',
        'contentTypes' => $contentTypes,
    ]);
})->name('visual-editor.editor');

// Temporary sandbox route for M1 (#311). Mounts an empty BlockEditorProvider
// to prove @wordpress/* packages import cleanly and the Gutenberg canvas
// renders. Removed once the real editor shell ships (M3+).
Route::get('/ve-sandbox', function () {
    return view('visual-editor::sandbox.index');
})->name('visual-editor.sandbox');

// Icon Block Phase 6 (#557) — admin icon-sets settings page.
//
// Server-rendered settings screen that lists uploaded icon sets and
// offers the upload / rename / delete forms. Access runs through the
// same `SiteEditorAccessGate` binding that protects the site editor
// SPA — the issue forbids introducing a new capability for this
// surface, so this is "the existing visual-editor management policy".
// Inline-styled like the install-gate page so it stands alone without
// the SPA's Vite bundle.
Route::middleware('web')
    ->group(function (): void {
        Route::get('/visual-editor/admin/icon-sets', function (Request $request, SiteEditorAccessGate $gate) {
            if ($denial = $gate->check($request)) {
                return $denial;
            }

            $registry = app(UploadedIconSetRegistry::class);

            return view('visual-editor::admin.icon-sets', [
                'sets'         => $registry->all(),
                'apiBase'      => '/visual-editor/api/admin/icon-sets',
                'maxKilobytes' => UploadIconSetRequest::MAX_ZIP_KILOBYTES,
            ]);
        })->name('visual-editor.admin.icon-sets');

        // #650 — Snippets admin page. Same access model as icon-sets:
        // static Blade shell, actions POST to the JSON API, gated by
        // SiteEditorAccessGate.
        Route::get('/visual-editor/admin/snippets', function (Request $request, SiteEditorAccessGate $gate) {
            if ($denial = $gate->check($request)) {
                return $denial;
            }

            return view('visual-editor::admin.snippets', [
                'apiBase' => '/visual-editor/api/snippets',
            ]);
        })->name('visual-editor.admin.snippets');
    });

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
