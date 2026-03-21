<?php

/**
 * StyleSourceIndicator Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\StyleSourceIndicator;

test( 'style source indicator can be instantiated with defaults', function (): void {
	$component = new StyleSourceIndicator();

	expect( $component->field )->toBe( '' )
		->and( $component->blockId )->toBe( 'dynamic' );
} );

test( 'style source indicator accepts field parameter', function (): void {
	$component = new StyleSourceIndicator( field: 'backgroundColor' );

	expect( $component->field )->toBe( 'backgroundColor' );
} );

test( 'style source indicator accepts blockId parameter', function (): void {
	$component = new StyleSourceIndicator( field: 'textColor', blockId: 'block-123' );

	expect( $component->field )->toBe( 'textColor' )
		->and( $component->blockId )->toBe( 'block-123' );
} );

test( 'style source indicator renders view', function (): void {
	$component = new StyleSourceIndicator( field: 'backgroundColor' );

	$view = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.style-source-indicator' );
} );
