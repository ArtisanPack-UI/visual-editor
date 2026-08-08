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
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.6.0
 */

declare( strict_types=1 );

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get( 'vendor/visual-editor-renderer-blade/{path}', function ( Request $request, string $path ) {
	$root = realpath( __DIR__ . '/../resources/assets' );

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

	// Mirrors the publish mapping: `frontend/*` comes from the frontend
	// directory, everything else from the block-library bundle, both of
	// which flatten into the same public URL prefix.
	$relative = str_starts_with( $path, 'frontend/' )
		? $path
		: 'block-library/' . $path;

	$file = realpath( $root . '/' . $relative );

	// `realpath()` resolves `..` segments and symlinks, so the prefix check
	// below rejects anything that escapes the bundled assets directory.
	abort_if( false === $file || ! is_file( $file ), 404 );
	abort_unless( str_starts_with( $file, $root . DIRECTORY_SEPARATOR ), 404 );

	// The URLs are not fingerprinted, so a long max-age would pin browsers
	// to the pre-upgrade CSS. Revalidation keeps upgrades instant while the
	// ETag / Last-Modified pair below still short-circuits to a 304.
	$response = response()->file( $file, [
		'Content-Type'           => $contentTypes[ $extension ],
		'Cache-Control'          => 'public, max-age=0, must-revalidate',
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
