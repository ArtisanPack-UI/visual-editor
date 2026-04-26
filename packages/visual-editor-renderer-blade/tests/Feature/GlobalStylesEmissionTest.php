<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	Cache::flush();
	$this->app->make( GlobalStylesEmissionTracker::class )->reset();
} );

it( 'auto-emits the compiled global-styles CSS inside <x-ve-blocks>', function () {
	VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'brand', 'name' => 'Brand', 'color' => '#abcdef' ] ],
			],
		],
		'styles'   => [],
	] );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $rendered )->toContain( '<style data-ve-global-styles>' )
		->toContain( '--wp--preset--color--brand: #abcdef' );
} );

it( 'auto-emits the compiled global-styles CSS inside <x-ve-template>', function () {
	VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'brand', 'name' => 'Brand', 'color' => '#abcdef' ] ],
			],
		],
		'styles'   => [],
	] );

	VisualEditorTemplate::create( [
		'slug'    => 'index',
		'title'   => 'Index',
		'content' => [ 'raw' => '', 'blocks' => [] ],
		'theme'   => 'artisanpack-base',
		'source'  => 'theme',
		'origin'  => 'theme',
	] );

	$rendered = Blade::render( '<x-ve-template slug="index" theme="artisanpack-base" />' );

	expect( $rendered )->toContain( '<style data-ve-global-styles>' )
		->toContain( '--wp--preset--color--brand: #abcdef' );
} );

it( 'emits the <style> block exactly once when multiple renderers fire on the same page', function () {
	VisualEditorGlobalStyles::create( [
		'theme'    => 'artisanpack-base',
		'version'  => 3,
		'settings' => [
			'color' => [
				'palette' => [ [ 'slug' => 'brand', 'name' => 'Brand', 'color' => '#abcdef' ] ],
			],
		],
		'styles'   => [],
	] );

	$page = Blade::render(
		'<x-ve-blocks :tree="$tree" /><x-ve-blocks :tree="$tree" /><x-ve-blocks :tree="$tree" />',
		[ 'tree' => [] ]
	);

	expect( substr_count( $page, '<style data-ve-global-styles>' ) )->toBe( 1 );
} );

it( 'falls back to the bundled defaults when no record exists', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $rendered )->toContain( '<style data-ve-global-styles>' )
		->toContain( '--wp--preset--color--primary' );
} );
