<?php

/**
 * TemplatePartsManager Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TemplatePartsManager;

test( 'template parts manager can be instantiated with pre-loaded parts', function (): void {
	$emptyParts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$component = new TemplatePartsManager( availableParts: $emptyParts );

	expect( $component->uuid )->toStartWith( 've-' )
		->and( $component->assignments )->toBeArray()
		->and( $component->partsByArea )->toBeArray()
		->and( $component->areaLabels )->toHaveKeys( [ 'header', 'footer', 'sidebar', 'custom' ] );
} );

test( 'template parts manager accepts custom assignments', function (): void {
	$assignments = [
		'header'  => 1,
		'footer'  => 2,
		'sidebar' => null,
		'custom'  => null,
	];

	$emptyParts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$component = new TemplatePartsManager( assignments: $assignments, availableParts: $emptyParts );

	expect( $component->assignments['header'] )->toBe( 1 )
		->and( $component->assignments['footer'] )->toBe( 2 )
		->and( $component->assignments['sidebar'] )->toBeNull();
} );

test( 'template parts manager accepts pre-loaded parts', function (): void {
	$parts = [
		'header'  => [ [ 'id' => 1, 'name' => 'Main Header', 'slug' => 'main-header' ] ],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$component = new TemplatePartsManager( availableParts: $parts );

	expect( $component->partsByArea['header'] )->toHaveCount( 1 )
		->and( $component->partsByArea['header'][0]['name'] )->toBe( 'Main Header' );
} );

test( 'template parts manager renders', function (): void {
	$parts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$view = $this->blade( '<x-ve-template-parts-manager :available-parts="$parts" />', [ 'parts' => $parts ] );

	expect( $view )->not->toBeNull();
} );

test( 'template parts manager renders title', function (): void {
	$parts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$this->blade( '<x-ve-template-parts-manager :available-parts="$parts" />', [ 'parts' => $parts ] )
		->assertSee( 'Template Parts' );
} );

test( 'template parts manager renders area labels', function (): void {
	$parts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$view = $this->blade( '<x-ve-template-parts-manager :available-parts="$parts" />', [ 'parts' => $parts ] );

	$view->assertSee( 'Create New Part' );
} );

test( 'template parts manager includes custom id in uuid', function (): void {
	$emptyParts = [
		'header'  => [],
		'footer'  => [],
		'sidebar' => [],
		'custom'  => [],
	];

	$component = new TemplatePartsManager( id: 'test-parts', availableParts: $emptyParts );

	expect( $component->uuid )->toContain( 'test-parts' );
} );
