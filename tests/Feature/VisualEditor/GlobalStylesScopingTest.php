<?php

/**
 * The global styles resolver is request-scoped (F-6), so a long-lived
 * worker (Octane) sees edited global styles and theme switches on the
 * next request instead of the snapshot taken at boot.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;

beforeEach( function (): void {
	$this->globalStyles = [ 'title' => 'First', 'settings' => [], 'styles' => [ 'color' => [ 'text' => '#111111' ] ] ];

	$this->filter = fn ( ?array $existing ): ?array => $this->globalStyles;

	addFilter( 'ap.visualEditor.globalStyles', $this->filter );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
} );

afterEach( function (): void {
	removeFilter( 'ap.visualEditor.globalStyles', $this->filter );
} );

it( 'resolves global styles afresh after a request boundary', function (): void {
	expect( app( GlobalStylesResolver::class )->raw()['styles']['color']['text'] )->toBe( '#111111' );

	$this->globalStyles['styles']['color']['text'] = '#222222';

	// Same request: the scoped instance is reused.
	expect( app( GlobalStylesResolver::class )->raw()['styles']['color']['text'] )->toBe( '#111111' );

	app()->forgetScopedInstances();

	expect( app( GlobalStylesResolver::class )->raw()['styles']['color']['text'] )->toBe( '#222222' );
} );

it( 'feeds block style variations from the fresh global styles', function (): void {
	$this->globalStyles['styles']['blocks'] = [ 'core/group' => [ 'variations' => [ 'card' => [ 'color' => [ 'text' => '#111111' ] ] ] ] ];

	expect( app( BlockStyleResolver::class )->css() )->toContain( 'color: #111111;' );

	$this->globalStyles['styles']['blocks']['core/group']['variations']['card']['color']['text'] = '#222222';
	app()->forgetScopedInstances();

	expect( app( BlockStyleResolver::class )->css() )->toContain( 'color: #222222;' )
		->not->toContain( '#111111' );
} );

it( 'lets static config win over the filter', function (): void {
	config()->set( 'artisanpack.visual-editor.site-editor.global-styles', [ 'title' => 'Static', 'settings' => [], 'styles' => [] ] );
	app()->forgetScopedInstances();

	expect( app( GlobalStylesResolver::class )->raw()['title'] )->toBe( 'Static' );
} );
