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
		->assertSet( 'context', 'visual-editor' )
		->assertSet( 'multiSelect', false )
		->assertSet( 'maxSelections', 1 )
		->assertSet( 'isOpen', false );
} );

it( 'sets context and opens via event', function (): void {
	Livewire::test( 'visual-editor::media-picker' )
		->call( 'open', 'featured-image' )
		->assertSet( 'context', 'featured-image' )
		->assertSet( 'isOpen', true )
		->assertDispatched( 'open-media-modal' );
} );

it( 'dispatches media selected event on selection', function (): void {
	$media = [
		[ 'id' => 1, 'url' => 'https://example.com/image.jpg' ],
	];

	Livewire::test( 'visual-editor::media-picker' )
		->call( 'onMediaSelected', $media, 'featured-image' )
		->assertDispatched( 've-media-selected' )
		->assertSet( 'isOpen', false );
} );

it( 'opens with default context', function (): void {
	Livewire::test( 'visual-editor::media-picker' )
		->call( 'open' )
		->assertSet( 'context', 'visual-editor' )
		->assertSet( 'isOpen', true );
} );
