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
		->assertSet( 'activeTab', 'layers' )
		->call( 'setTab', 'settings' )
		->assertSet( 'activeTab', 'settings' );
} );

test( 'sidebar displays blocks tab by default', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->assertSee( 'Blocks' )
		->assertSee( 'Sections' )
		->assertSee( 'Layers' )
		->assertSee( 'Settings' );
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

test( 'sidebar shows layers placeholder on layers tab', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->call( 'setTab', 'layers' )
		->assertSee( 'Layer navigation will be available here.' );
} );

test( 'sidebar shows settings placeholder on settings tab', function (): void {
	Livewire::test( 'visual-editor::sidebar' )
		->call( 'setTab', 'settings' )
		->assertSee( 'Content settings will be available here.' );
} );
