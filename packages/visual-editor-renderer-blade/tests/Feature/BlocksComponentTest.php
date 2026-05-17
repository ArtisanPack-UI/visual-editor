<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the x-ve-blocks component from an array tree', function () {
	$tree = [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello from Blade' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">Hello from Blade</p>' );
} );

it( 'accepts a JSON string tree', function () {
	$json = json_encode( [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'JSON string' ],
			'innerBlocks' => [],
		],
	], JSON_THROW_ON_ERROR );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $json ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">JSON string</p>' );
} );

it( 'renders an empty output when the tree is null and cms-framework is not installed', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => null ] );

	// #434: the legacy `GlobalStylesCssProvider` used to emit bundled
	// defaults when no DB record existed, so a null tree still produced
	// a `<style>` block. Without cms-framework's emitter in this test
	// environment, the resolver returns an empty string and no `<style>`
	// is rendered.
	expect( $rendered )->not->toContain( 'data-ve-global-styles' );
	expect( trim( $rendered ) )->toBe( '' );
} );

it( 'publishes block views under the visual-editor-blade-views tag', function () {
	$tag = 'visual-editor-blade-views';

	$artisan = $this->artisan( 'vendor:publish', [ '--tag' => $tag, '--force' => true ] );

	$artisan->assertExitCode( 0 );
} );

it( 'passes the nav-block tree through unchanged when no defaultTheme is supplied (Keystone #51)', function () {
	// Without a theme, the navigation resolver has no `(theme, location)`
	// to query against, so the tree should pass through. The block's
	// own Blade view renders an empty `<nav>` — same as before #51.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ '__unstableLocation' => 'primary' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'wp-block-navigation' );
	// Empty container — no menu items projected.
	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toContain( '<ul class="wp-block-navigation__container"></ul>' );
} );

it( 'passes the nav-block tree through unchanged when cms-framework is not installed (Keystone #51)', function () {
	// cms-framework's `MenuLocationAssignment` model isn't autoloaded in
	// this Testbench environment, so the resolver's `class_exists` guard
	// short-circuits the lookup. With a theme present but no DB to
	// query, the tree still passes through — no menu items, no error.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [ '__unstableLocation' => 'primary' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" default-theme="jmwd-default" />',
		[ 'tree' => $tree ],
	);

	expect( $rendered )->toContain( 'wp-block-navigation' );
	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toContain( '<ul class="wp-block-navigation__container"></ul>' );
} );

it( 'preserves authored innerBlocks on a nav block instead of overwriting them (Keystone #51)', function () {
	// A nav block authored with explicit nav-links keeps them — the
	// resolver only projects menu items when the tree is empty.
	$tree = [
		[
			'clientId'    => 'nav-1',
			'name'        => 'core/navigation',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'clientId'    => 'l-1',
					'name'        => 'core/navigation-link',
					'attributes'  => [ 'label' => 'Authored', 'url' => '/authored' ],
					'innerBlocks' => [],
				],
			],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'Authored' );
	expect( $rendered )->toContain( 'href="/authored"' );
} );
