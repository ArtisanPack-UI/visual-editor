<?php

/**
 * Front-end asset delivery route for the Blade renderer package.
 *
 * `<x-ve-blocks-styles />` links to `/vendor/visual-editor-renderer-blade/…`
 * for the bundled block-library CSS and the per-block `frontend/*` styles
 * and scripts. Historically those files only existed after the consumer ran
 * `vendor:publish --tag=visual-editor-renderer-blade-assets`, so a fresh
 * install served Laravel's 404 page for every stylesheet and the front end
 * rendered unstyled (#699).
 *
 * This route streams the files straight out of the package so the assets
 * work with no publish step, and package upgrades take effect immediately.
 * When a consumer *has* published the assets, the web server serves the
 * static file from `public/` and this route is never reached — publishing
 * remains a valid (and slightly faster) opt-in.
 *
 * `php artisan visual-editor:publish-assets` publishes content-hashed copies
 * and a manifest (#1033). This route is the fallback for hosts that haven't
 * run it. It also answers fingerprinted URLs: a page cached before a deploy
 * can still ask for a hash that is no longer on disk, and gets the current
 * file rather than a 404. The generated global-styles file (#1032) has its
 * own route, since it lives in the cache rather than the package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\GeneratedStylesheets;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get( 'vendor/visual-editor-renderer-blade/' . GeneratedStylesheets::DIRECTORY . '/{hash}.css', function ( GeneratedStylesheets $stylesheets, string $hash ) {
	$css = $stylesheets->get( $hash );

	abort_if( null === $css, 404 );

	// The file name is a hash of this content, so it can never go stale.
	return response( $css, 200, [
		'Content-Type'           => 'text/css',
		'Cache-Control'          => 'public, max-age=31536000, immutable',
		'X-Content-Type-Options' => 'nosniff',
	] );
} )
	->where( 'hash', '[a-f0-9]{20}' )
	->name( 'visual-editor-renderer-blade.generated-stylesheet' );

Route::get( 'vendor/visual-editor-renderer-blade/{path}', function ( Request $request, string $path ) {
	$root = realpath( FrontendAssets::root() );

	abort_if( false === $root, 404 );

	// Extensions are allow-listed rather than inferred so the route can
	// never be coaxed into serving PHP or another executable payload out
	// of the package directory.
	$contentTypes = [
		'css' => 'text/css',
		'js'  => 'text/javascript',
	];

	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	abort_unless( isset( $contentTypes[ $extension ] ), 404 );

	[ $assetPath, $requestedHash ] = FrontendAssets::parseFingerprint( $path ) ?? [ $path, null ];

	// Mirrors the publish mapping: `frontend/*` comes from the frontend
	// directory, everything else from the block-library bundle, both of
	// which flatten into the same public URL prefix.
	$file = realpath( $root . '/' . FrontendAssets::relativeSource( $assetPath ) );

	// `realpath()` resolves `..` segments and symlinks, so the prefix check
	// below rejects anything that escapes the bundled assets directory.
	abort_if( false === $file || ! is_file( $file ), 404 );
	abort_unless( str_starts_with( $file, $root . DIRECTORY_SEPARATOR ), 404 );

	// Plain URLs aren't fingerprinted, so a long max-age would pin browsers
	// to the pre-upgrade CSS. Revalidation keeps upgrades instant while the
	// ETag / Last-Modified pair below still short-circuits to a 304. A URL
	// whose hash matches the file can never go stale, so it's cached for
	// good (#1033).
	$isCurrent = null !== $requestedHash && hash_equals( FrontendAssets::hash( $file ), $requestedHash );

	$response = response()->file( $file, [
		'Content-Type'           => $contentTypes[ $extension ],
		'Cache-Control'          => $isCurrent ? 'public, max-age=31536000, immutable' : 'public, max-age=0, must-revalidate',
		'X-Content-Type-Options' => 'nosniff',
	] );

	$response->setAutoLastModified();
	$response->setAutoEtag();
	$response->isNotModified( $request );

	return $response;
} )
	// Restricted to the characters a bundled asset path can actually contain.
	// Containment is enforced by the `realpath()` check in the closure — this
	// pattern is the outer guard that keeps null bytes and control characters
	// from ever reaching `realpath()`, which throws a ValueError (a 500) on
	// them rather than returning false.
	->where( 'path', '[A-Za-z0-9._\-\/]+' )
	->name( 'visual-editor-renderer-blade.asset' );
