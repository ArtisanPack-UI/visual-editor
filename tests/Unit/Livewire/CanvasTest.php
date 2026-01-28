<?php

declare( strict_types=1 );

use Livewire\Livewire;

test( 'canvas component renders successfully', function (): void {
	Livewire::test( 'visual-editor::canvas' )
		->assertStatus( 200 );
} );

test( 'canvas shows empty state when no sections', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->assertSee( 'Start building your page' )
		->assertSee( 'Add blocks from the sidebar to begin creating content.' );
} );

test( 'canvas renders sections when provided', function (): void {
	$sections = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertDontSee( 'Start building your page' )
		->assertSee( 'Heading' )
		->assertSee( 'Text' );
} );

test( 'canvas can select a block', function (): void {
	$sections = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->assertSet( 'activeBlockId', null )
		->call( 'selectBlock', 've-1' )
		->assertSet( 'activeBlockId', 've-1' );
} );

test( 'canvas inserts block on block-insert event', function (): void {
	Livewire::test( 'visual-editor::canvas', [ 'sections' => [] ] )
		->dispatch( 'block-insert', type: 'heading' )
		->assertNotSet( 'sections', [] )
		->assertDispatched( 'sections-updated' );
} );

test( 'canvas reorders sections', function (): void {
	$sections = [
		[ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ],
		[ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ],
	];

	Livewire::test( 'visual-editor::canvas', [ 'sections' => $sections ] )
		->call( 'reorderSections', [
			[ 'value' => 1 ],
			[ 'value' => 0 ],
		] )
		->assertDispatched( 'sections-updated' );
} );
