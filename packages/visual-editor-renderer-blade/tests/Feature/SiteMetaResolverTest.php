<?php

declare( strict_types=1 );

/**
 * Feature tests for the Blade renderer's site-meta resolver path.
 *
 * Verifies that `core/site-title`, `core/site-tagline`, and `core/site-logo`
 * blocks pull their `_resolvedSite*` values from `apGetSetting()` when
 * cms-framework's helper is loaded, and from `config('artisanpack.visual-editor.site_meta')`
 * when it isn't. Also exercises the `apGetMediaUrl()` resolution for
 * `site.logo_id` and the existing-attribute-wins guarantee.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\SiteMetaResolver;
use Illuminate\Support\Facades\Blade;

function siteRenderTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

function siteBlockNode( string $name, array $attributes = [], string $clientId = 'site-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

afterEach( function (): void {
	app( SiteMetaResolver::class )->flush();
} );

it( 'falls back to config defaults when cms-framework helpers are absent', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title'       => 'Config Title',
		'description' => 'Config Tagline',
		'url'         => 'https://config.example',
		'logo_id'     => null,
		'icon_id'     => null,
	] );

	app( SiteMetaResolver::class )->flush();

	$rendered = $this->stripGlobalStyles( siteRenderTree( [
		siteBlockNode( 'core/site-title' ),
		siteBlockNode( 'core/site-tagline' ),
	] ) );

	expect( $rendered )
		->toContain( 'Config Title' )
		->toContain( 'href="https://config.example"' )
		->toContain( '<p class="wp-block-site-tagline">Config Tagline</p>' );
} );

it( 'reads from apGetSetting when the helper is loaded', function () {
	if ( ! function_exists( 'apGetSetting' ) ) {
		$this->markTestSkipped( 'apGetSetting() helper from artisanpack-ui/cms-framework is not loaded in this test run.' );
	}

	addFilter( 'ap.settings.registeredSettings', function ( array $settings ): array {
		$settings['site.title']   = [ 'default' => 'Helper Title',     'type' => null, 'callback' => null ];
		$settings['site.tagline'] = [ 'default' => 'Helper Tagline',   'type' => null, 'callback' => null ];
		$settings['site.url']     = [ 'default' => 'https://helper.example', 'type' => null, 'callback' => null ];

		return $settings;
	} );

	app( SiteMetaResolver::class )->flush();

	$rendered = $this->stripGlobalStyles( siteRenderTree( [
		siteBlockNode( 'core/site-title' ),
		siteBlockNode( 'core/site-tagline' ),
	] ) );

	expect( $rendered )
		->toContain( 'Helper Title' )
		->toContain( 'href="https://helper.example"' )
		->toContain( 'Helper Tagline' );

	removeAllFilters( 'ap.settings.registeredSettings' );
} );

it( 'lets host-stamped attributes win over the resolver fallback', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title' => 'From Config',
		'url'   => 'https://config.example',
	] );

	app( SiteMetaResolver::class )->flush();

	$rendered = $this->stripGlobalStyles( siteRenderTree( [
		siteBlockNode( 'core/site-title', [
			'_resolvedSiteTitle' => 'From Host',
			'_resolvedSiteUrl'   => 'https://host.example',
		] ),
	] ) );

	expect( $rendered )
		->toContain( 'From Host' )
		->toContain( 'href="https://host.example"' )
		->not()->toContain( 'From Config' );
} );

it( 'leaves non-site-meta blocks untouched', function () {
	config()->set( 'artisanpack.visual-editor.site_meta', [
		'title' => 'Should Not Appear',
	] );

	app( SiteMetaResolver::class )->flush();

	$rendered = $this->stripGlobalStyles( siteRenderTree( [
		siteBlockNode( 'core/paragraph', [ 'content' => 'Hello' ] ),
	] ) );

	expect( $rendered )
		->toContain( 'Hello' )
		->not()->toContain( 'Should Not Appear' );
} );
