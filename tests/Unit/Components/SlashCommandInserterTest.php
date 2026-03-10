<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\SlashCommandInserter;

test( 'slash command inserter can be instantiated with defaults', function (): void {
	$component = new SlashCommandInserter();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->blocks )->toBe( [] );
} );

test( 'slash command inserter accepts custom props', function (): void {
	$blocks    = [
		[ 'name' => 'paragraph', 'label' => 'Paragraph', 'keywords' => [ 'text' ] ],
		[ 'name' => 'heading', 'label' => 'Heading', 'keywords' => [ 'title' ] ],
	];
	$component = new SlashCommandInserter(
		id: 'slash',
		blocks: $blocks,
	);

	expect( $component->uuid )->toContain( 'slash' );
	expect( $component->blocks )->toBe( $blocks );
} );

test( 'slash command inserter renders', function (): void {
	$view = $this->blade( '<x-ve-slash-command-inserter />' );
	expect( $view )->not->toBeNull();
} );

test( 'slash command inserter renders with listbox role', function (): void {
	$this->blade( '<x-ve-slash-command-inserter />' )
		->assertSee( 'role="listbox"', false );
} );

test( 'slash command inserter renders no match message', function (): void {
	$this->blade( '<x-ve-slash-command-inserter />' )
		->assertSee( 'No matching blocks found.' );
} );
