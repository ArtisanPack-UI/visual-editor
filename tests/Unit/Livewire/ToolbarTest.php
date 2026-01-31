<?php

declare( strict_types=1 );

use Livewire\Livewire;

test( 'toolbar component renders successfully', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )->assertStatus( 200 );
} );

test( 'toolbar displays content title', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'My Test Page',
		'contentStatus' => 'draft',
	] )->assertSee( 'My Test Page' );
} );

test( 'toolbar displays content status', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'published',
	] )->assertSee( 'Published' );
} );

test( 'toolbar displays save status text', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )->assertSee( 'Saved' );
} );

test( 'toolbar displays saving status text', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saving',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )->assertSee( 'Saving...' );
} );

test( 'toolbar displays unsaved status text', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'unsaved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )->assertSee( 'Unsaved changes' );
} );

test( 'toolbar save dispatches editor-save event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'save' )
		->assertDispatched( 'editor-save' );
} );

test( 'toolbar publish dispatches editor-publish event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'publish' )
		->assertDispatched( 'editor-publish' );
} );

test( 'toolbar undo dispatches editor-undo event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'undo' )
		->assertDispatched( 'editor-undo' );
} );

test( 'toolbar redo dispatches editor-redo event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'redo' )
		->assertDispatched( 'editor-redo' );
} );

test( 'toolbar preview dispatches editor-preview event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'preview' )
		->assertDispatched( 'editor-preview' );
} );

test( 'toolbar toggleSettings dispatches editor-toggle-settings event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'toggleSettings' )
		->assertDispatched( 'editor-toggle-settings' );
} );

// =========================================
// Sidebar Toggle Tests
// =========================================

test( 'toolbar renders with sidebar state props', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
		'sidebarOpen'   => true,
		'settingsOpen'  => false,
	] )->assertStatus( 200 );
} );

test( 'toolbar toggleSidebar dispatches editor-toggle-sidebar event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->call( 'toggleSidebar' )
		->assertDispatched( 'editor-toggle-sidebar' );
} );

test( 'toolbar accepts sidebar and settings open props', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
		'sidebarOpen'   => false,
		'settingsOpen'  => true,
	] )
		->assertSet( 'sidebarOpen', false )
		->assertSet( 'settingsOpen', true );
} );

// =========================================
// Undo/Redo State Tests
// =========================================

test( 'toolbar initializes with undo and redo disabled', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->assertSet( 'canUndo', false )
		->assertSet( 'canRedo', false );
} );

test( 'toolbar updates canUndo on undo-redo-state-changed event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->dispatch( 'undo-redo-state-changed', canUndo: true, canRedo: false )
		->assertSet( 'canUndo', true )
		->assertSet( 'canRedo', false );
} );

test( 'toolbar updates canRedo on undo-redo-state-changed event', function (): void {
	Livewire::test( 'visual-editor::toolbar', [
		'saveStatus'    => 'saved',
		'contentTitle'  => 'Test Page',
		'contentStatus' => 'draft',
	] )
		->dispatch( 'undo-redo-state-changed', canUndo: false, canRedo: true )
		->assertSet( 'canUndo', false )
		->assertSet( 'canRedo', true );
} );
