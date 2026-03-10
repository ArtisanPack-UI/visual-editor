<?php

/**
 * Media Picker Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use Livewire\Livewire;

beforeEach( function (): void {
	// Override the media namespace to point to our test stubs.
	// The real MediaModal requires icon assets (FontAwesome) that aren't
	// available in the package test environment. Our stub renders minimal
	// HTML so we can test the media-picker's own logic.
	Livewire::addNamespace(
		'media',
		classNamespace: 'Tests\\Stubs',
	);
} );

it( 'mounts with default properties', function (): void {
	Livewire::test( 'visual-editor::media-picker' )
		->assertSet( 'context', '' )
		->assertSet( 'multiSelect', true )
		->assertSet( 'maxSelections', 0 );
} );

it( 'sets context and dispatches open-media-modal via event', function (): void {
	Livewire::test( 'visual-editor::media-picker' )
		->call( 'open', 'featured-image' )
		->assertSet( 'context', 'featured-image' )
		->assertDispatched( 'open-media-modal' );
} );

it( 'opens with default context', function (): void {
	Livewire::test( 'visual-editor::media-picker' )
		->call( 'open' )
		->assertSet( 'context', 'visual-editor' )
		->assertDispatched( 'open-media-modal' );
} );
