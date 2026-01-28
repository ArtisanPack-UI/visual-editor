<?php

declare( strict_types=1 );

use Livewire\Livewire;

test( 'status bar component renders successfully', function (): void {
	Livewire::test( 'visual-editor::status-bar' )
		->assertStatus( 200 );
} );

test( 'status bar shows saved status', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'saveStatus' => 'saved' ] )
		->assertSee( 'Saved' );
} );

test( 'status bar shows saving status', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'saveStatus' => 'saving' ] )
		->assertSee( 'Saving...' );
} );

test( 'status bar shows unsaved status', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'saveStatus' => 'unsaved' ] )
		->assertSee( 'Unsaved changes' );
} );

test( 'status bar shows word count', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'wordCount' => 42 ] )
		->assertSee( '42 words' );
} );

test( 'status bar shows singular word count', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'wordCount' => 1 ] )
		->assertSee( '1 word' );
} );

test( 'status bar shows content status', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'contentStatus' => 'published' ] )
		->assertSee( 'Published' );
} );

test( 'status bar shows last saved time', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'lastSaved' => '2 minutes ago' ] )
		->assertSee( 'Last saved: 2 minutes ago' );
} );

test( 'status bar hides last saved when empty', function (): void {
	Livewire::test( 'visual-editor::status-bar', [ 'lastSaved' => '' ] )
		->assertDontSee( 'Last saved:' );
} );
