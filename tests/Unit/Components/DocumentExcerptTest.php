<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DocumentExcerpt;

test( 'document excerpt can be instantiated with defaults', function (): void {
	$component = new DocumentExcerpt();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->metaKey )->toBe( 'excerpt' );
	expect( $component->label )->toBeNull();
	expect( $component->placeholder )->toBeNull();
	expect( $component->maxLength )->toBeNull();
} );

test( 'document excerpt accepts custom props', function (): void {
	$component = new DocumentExcerpt(
		id: 'summary',
		metaKey: 'summary',
		label: 'Summary',
		placeholder: 'Write a summary',
		maxLength: 160,
	);

	expect( $component->uuid )->toContain( 'summary' );
	expect( $component->metaKey )->toBe( 'summary' );
	expect( $component->label )->toBe( 'Summary' );
	expect( $component->placeholder )->toBe( 'Write a summary' );
	expect( $component->maxLength )->toBe( 160 );
} );

test( 'document excerpt renders', function (): void {
	$view = $this->blade( '<x-ve-document-excerpt />' );
	expect( $view )->not->toBeNull();
} );

test( 'document excerpt renders label', function (): void {
	$this->blade( '<x-ve-document-excerpt />' )
		->assertSee( 'Excerpt' );
} );

test( 'document excerpt renders textarea', function (): void {
	$this->blade( '<x-ve-document-excerpt />' )
		->assertSee( '<textarea', false );
} );

test( 'document excerpt renders placeholder', function (): void {
	$this->blade( '<x-ve-document-excerpt />' )
		->assertSee( 'Write an excerpt' );
} );

test( 'document excerpt renders character count when max length is set', function (): void {
	$view = $this->blade( '<x-ve-document-excerpt :max-length="160" />' );

	$view->assertSee( '/ 160' );
	$view->assertSee( 'characters' );
} );

test( 'document excerpt does not render character count without max length', function (): void {
	$view = $this->blade( '<x-ve-document-excerpt />' );

	$view->assertDontSee( 'characters' );
} );

test( 'document excerpt renders maxlength attribute when set', function (): void {
	$this->blade( '<x-ve-document-excerpt :max-length="160" />' )
		->assertSee( 'maxlength="160"', false );
} );

test( 'document excerpt renders meta key binding', function (): void {
	$view = $this->blade( '<x-ve-document-excerpt />' );

	$view->assertSee( 'getMeta', false );
} );
