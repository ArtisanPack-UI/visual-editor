<?php

/**
 * Feature tests for `ArtisanPackUI\VisualEditor\Support\HookAliases`.
 *
 * Confirms every hook renamed in #664 continues to fire old-name
 * subscribers when the canonical (new-name) hook is applied. Covers
 * `resources` in depth plus three additional families
 * (`templates`, `renderedBlock`, `loginout.envelope`) as a spot-check;
 * exhaustive per-hook coverage lives in the resolver / renderer tests
 * that already fire each hook.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.5.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\HookAliases;

afterEach( function (): void {
	removeAllFilters( 'ap.visual-editor.resources' );
	removeAllFilters( 'ap.visualEditor.resources' );
	removeAllFilters( 'ap.visual-editor.templates' );
	removeAllFilters( 'ap.visualEditor.templates' );
	removeAllFilters( 'ap.visual-editor.rendered-block' );
	removeAllFilters( 'ap.visualEditor.renderedBlock' );
	removeAllFilters( 'ap.visual-editor.loginout.envelope' );
	removeAllFilters( 'ap.visualEditor.loginout.envelope' );
} );

it( 'routes old-name resources subscribers when the new name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
		return array_merge( [ 'posts' => 'App\\Models\\Post' ], $resources );
	} );

	$result = applyFilters( 'ap.visualEditor.resources', [] );

	expect( $result )->toHaveKey( 'posts' );
} );

it( 'routes old-name templates subscribers when the new name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visual-editor.templates', function ( array $templates ): array {
		return array_merge( [ 'front-page' => [ 'slug' => 'front-page' ] ], $templates );
	} );

	$result = applyFilters( 'ap.visualEditor.templates', [] );

	expect( $result )->toHaveKey( 'front-page' );
} );

it( 'routes old-name renderedBlock subscribers when the new name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visual-editor.rendered-block', function ( string $html ): string {
		return $html . '::filtered';
	} );

	$result = applyFilters( 'ap.visualEditor.renderedBlock', 'html' );

	expect( $result )->toBe( 'html::filtered' );
} );

it( 'routes old-name loginout envelope subscribers when the new name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visual-editor.loginout.envelope', function ( array $envelope ): array {
		$envelope['touched'] = true;

		return $envelope;
	} );

	$result = applyFilters( 'ap.visualEditor.loginout.envelope', [] );

	expect( $result )->toHaveKey( 'touched' );
} );

it( 'is idempotent — a second registerAll() does not double-fire subscribers', function (): void {
	HookAliases::registerAll();
	HookAliases::registerAll();

	$calls = 0;
	addFilter( 'ap.visual-editor.resources', function ( array $resources ) use ( &$calls ): array {
		$calls++;

		return $resources;
	} );

	applyFilters( 'ap.visualEditor.resources', [] );

	expect( $calls )->toBe( 1 );
} );

/*
 * Reverse-direction coverage — a legacy host may still call
 * applyFilters() against the OLD name (e.g. downstream code compiled
 * before the rename shipped). Subscribers registered on the canonical
 * NEW name must still fire so hosts do not lose behavior mid-migration.
 * These mirror the four OLD→NEW cases above.
 */

it( 'routes new-name resources subscribers when the old name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visualEditor.resources', function ( array $resources ): array {
		return array_merge( [ 'posts' => 'App\\Models\\Post' ], $resources );
	} );

	$result = applyFilters( 'ap.visual-editor.resources', [] );

	expect( $result )->toHaveKey( 'posts' );
} );

it( 'routes new-name templates subscribers when the old name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visualEditor.templates', function ( array $templates ): array {
		return array_merge( [ 'front-page' => [ 'slug' => 'front-page' ] ], $templates );
	} );

	$result = applyFilters( 'ap.visual-editor.templates', [] );

	expect( $result )->toHaveKey( 'front-page' );
} );

it( 'routes new-name renderedBlock subscribers when the old name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visualEditor.renderedBlock', function ( string $html ): string {
		return $html . '::filtered';
	} );

	$result = applyFilters( 'ap.visual-editor.rendered-block', 'html' );

	expect( $result )->toBe( 'html::filtered' );
} );

it( 'routes new-name loginout envelope subscribers when the old name is applied', function (): void {
	HookAliases::registerAll();

	addFilter( 'ap.visualEditor.loginout.envelope', function ( array $envelope ): array {
		$envelope['touched'] = true;

		return $envelope;
	} );

	$result = applyFilters( 'ap.visual-editor.loginout.envelope', [] );

	expect( $result )->toHaveKey( 'touched' );
} );
