<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\KeyboardShortcuts;

test( 'keyboard shortcuts can be instantiated with defaults', function (): void {
	$component = new KeyboardShortcuts();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->shortcuts )->toBe( [] );
	expect( $component->showHelpModal )->toBeTrue();
	expect( $component->helpShortcut )->toBe( '?' );
	expect( $component->title )->not->toBeNull();
} );

test( 'keyboard shortcuts accepts custom props', function (): void {
	$shortcuts = [
		[
			'name'        => 'save',
			'keys'        => 'mod+s',
			'description' => 'Save content',
			'category'    => 'global',
		],
	];

	$component = new KeyboardShortcuts(
		id: 'editor',
		shortcuts: $shortcuts,
		showHelpModal: false,
		helpShortcut: 'F1',
		title: 'Editor Shortcuts',
	);

	expect( $component->uuid )->toContain( 'editor' );
	expect( $component->shortcuts )->toHaveCount( 1 );
	expect( $component->showHelpModal )->toBeFalse();
	expect( $component->helpShortcut )->toBe( 'F1' );
	expect( $component->title )->toBe( 'Editor Shortcuts' );
} );

test( 'keyboard shortcuts has valid default categories', function (): void {
	expect( KeyboardShortcuts::DEFAULT_CATEGORIES )->toBe( [
		'global',
		'block',
		'selection',
		'navigation',
		'insertion',
	] );
} );

test( 'keyboard shortcuts renders', function (): void {
	$view = $this->blade( '<x-ve-keyboard-shortcuts :showHelpModal="false" />' );
	expect( $view )->not->toBeNull();
} );

test( 'keyboard shortcuts renders without help modal', function (): void {
	$view = $this->blade( '<x-ve-keyboard-shortcuts :showHelpModal="false" />' );
	expect( $view )->not->toBeNull();
} );
