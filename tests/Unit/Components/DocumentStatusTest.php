<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentStatus;
use ArtisanPackUI\VisualEditor\View\Components\EditorState;

test( 'document status can be instantiated with defaults', function (): void {
	$component = new DocumentStatus();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->status )->toBe( 'draft' );
	expect( $component->scheduledDate )->toBeNull();
} );

test( 'document status accepts custom props', function (): void {
	$component = new DocumentStatus(
		id: 'status',
		status: 'scheduled',
		scheduledDate: '2026-03-01 10:00',
	);

	expect( $component->uuid )->toContain( 'status' );
	expect( $component->status )->toBe( 'scheduled' );
	expect( $component->scheduledDate )->toBe( '2026-03-01 10:00' );
} );

test( 'document status falls back to draft for invalid status', function (): void {
	$component = new DocumentStatus( status: 'invalid' );

	expect( $component->status )->toBe( 'draft' );
} );

test( 'document status renders', function (): void {
	$view = $this->blade( '<x-ve-document-status />' );
	expect( $view )->not->toBeNull();
} );

test( 'document status renders status select', function (): void {
	$this->blade( '<x-ve-document-status />' )
		->assertSee( '<select', false );
} );

test( 'document status renders all status options', function (): void {
	$view = $this->blade( '<x-ve-document-status />' );

	$view->assertSee( 'Draft' );
	$view->assertSee( 'Published' );
	$view->assertSee( 'Scheduled' );
	$view->assertSee( 'Pending Review' );
} );

test( 'document status renders schedule date section', function (): void {
	$this->blade( '<x-ve-document-status />' )
		->assertSee( 'Schedule date' );
} );

test( 'document status renders date hint', function (): void {
	$this->blade( '<x-ve-document-status />' )
		->assertSee( 'Set the date and time for publishing.' );
} );

test( 'document status renders label', function (): void {
	$this->blade( '<x-ve-document-status />' )
		->assertSee( 'Document status' );
} );

test( 'document status uses PHP constants for status keys', function (): void {
	$view = $this->blade( '<x-ve-document-status />' );

	foreach ( EditorState::DOCUMENT_STATUSES as $status ) {
		$view->assertSee( 'value="' . $status . '"', false );
	}
} );
