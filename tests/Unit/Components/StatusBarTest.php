<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\StatusBar;

test( 'status bar can be instantiated with defaults', function (): void {
	$component = new StatusBar();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->showBlockCount )->toBeTrue();
	expect( $component->showWordCount )->toBeTrue();
	expect( $component->showSaveStatus )->toBeTrue();
	expect( $component->showLastSaved )->toBeTrue();
} );

test( 'status bar accepts custom props', function (): void {
	$component = new StatusBar(
		id: 'status',
		showBlockCount: false,
		showWordCount: false,
		showSaveStatus: false,
		showLastSaved: false,
	);

	expect( $component->uuid )->toContain( 'status' );
	expect( $component->showBlockCount )->toBeFalse();
	expect( $component->showWordCount )->toBeFalse();
	expect( $component->showSaveStatus )->toBeFalse();
	expect( $component->showLastSaved )->toBeFalse();
} );

test( 'status bar renders', function (): void {
	$view = $this->blade( '<x-ve-status-bar />' );
	expect( $view )->not->toBeNull();
} );

test( 'status bar renders with status role', function (): void {
	$this->blade( '<x-ve-status-bar />' )
		->assertSee( 'role="status"', false );
} );

test( 'status bar renders block count by default', function (): void {
	$this->blade( '<x-ve-status-bar />' )
		->assertSee( 'blockCountLabel', false );
} );

test( 'status bar renders save status by default', function (): void {
	$this->blade( '<x-ve-status-bar />' )
		->assertSee( 'saveStatusLabel', false );
} );

test( 'status bar hides block count when disabled', function (): void {
	$this->blade( '<x-ve-status-bar :show-block-count="false" :show-word-count="false" />' )
		->assertDontSee( 'x-text="blockCountLabel"', false );
} );

test( 'status bar renders save status constants instead of magic strings', function (): void {
	$view = $this->blade( '<x-ve-status-bar />' );

	$view->assertSee( 'SAVE_STATUS: Object.freeze(', false );
	$view->assertSee( 'SAVE_STATUS.SAVED', false );
	$view->assertSee( 'SAVE_STATUS.UNSAVED', false );
	$view->assertSee( 'SAVE_STATUS.SAVING', false );
	$view->assertSee( 'SAVE_STATUS.ERROR', false );
} );
