<?php

declare( strict_types=1 );

use Livewire\Livewire;

test( 'sidebar component renders successfully', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertStatus( 200 );
} );

test( 'sidebar initializes with default state', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSet( 'isOpen', true )
		->assertSet( 'activeTab', 'blocks' )
		->assertSet( 'blockSearch', '' );
} );

test( 'sidebar can toggle open and closed', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSet( 'isOpen', true )
		->call( 'toggle' )
		->assertSet( 'isOpen', false )
		->call( 'toggle' )
		->assertSet( 'isOpen', true );
} );

test( 'sidebar can switch tabs', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSet( 'activeTab', 'blocks' )
		->call( 'setTab', 'sections' )
		->assertSet( 'activeTab', 'sections' )
		->call( 'setTab', 'layers' )
		->assertSet( 'activeTab', 'layers' );
} );

test( 'sidebar displays blocks tab by default', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSee( 'Blocks' )
		->assertSee( 'Sections' )
		->assertSee( 'Layers' )
		->assertDontSee( 'Settings' );
} );

test( 'sidebar shows block search input on blocks tab', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSeeHtml( 'placeholder="Search blocks..."' );
} );

test( 'sidebar insert block dispatches event', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->call( 'insertBlock', 'heading' )
		->assertDispatched( 'block-insert', type: 'heading' );
} );

test( 'sidebar can accept active tab prop', function (): void {
	Livewire::test( 'visual-editor::sidebar', [ 'activeTab' => 'sections' ] )
		->assertSet( 'activeTab', 'sections' );
} );

test( 'sidebar shows empty state on layers tab with no blocks', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->call( 'setTab', 'layers' )
		->assertSee( 'No blocks on the canvas yet.' );
} );

test( 'sidebar shows layers list with blocks', function (): void {
	$blocks = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::sidebar', [ 'blocks' => $blocks ] )
		->call( 'setTab', 'layers' )
		->assertSee( 'Heading' )
		->assertSee( 'Text' )
		->assertDontSee( 'No blocks on the canvas yet.' );
} );

test( 'sidebar selectLayerBlock dispatches block-selected event', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->call( 'selectLayerBlock', 've-1' )
		->assertDispatched( 'block-selected', blockId: 've-1' );
} );

test( 'sidebar layers shows block type name from registry', function (): void {
	$blocks = [
		[ 'id' => 've-1', 'type' => 'image', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::sidebar', [ 'blocks' => $blocks ] )
		->call( 'setTab', 'layers' )
		->assertSee( 'Image' );
} );

// =========================================
// Section Tab Tests
// =========================================

test( 'sidebar sections tab displays grouped sections', function (): void {
	Livewire::test( 'visual-editor::sidebar', [ 'activeTab' => 'sections' ] )
		->assertSee( 'Headers' )
		->assertSee( 'Content' )
		->assertSee( 'Hero' );
} );

test( 'sidebar sections tab shows section descriptions', function (): void {
	Livewire::test( 'visual-editor::sidebar', [ 'activeTab' => 'sections' ] )
		->assertSee( 'Large hero section with headline and call to action' );
} );

test( 'sidebar sections tab shows search input', function (): void {
	Livewire::test( 'visual-editor::sidebar', [ 'activeTab' => 'sections' ] )
		->assertSeeHtml( 'placeholder="Search sections..."' );
} );

test( 'sidebar section search filters sections by name', function (): void {
	$component = Livewire::test( 'visual-editor::sidebar', [ 'activeTab' => 'sections' ] )
		->set( 'sectionSearch', 'hero' );

	$component->assertSee( 'Hero' )
		->assertDontSee( 'Statistics' );
} );

test( 'sidebar initializes with empty section search', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSet( 'sectionSearch', '' );
} );
