<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\LinkControl;

test( 'link control can be instantiated with defaults', function (): void {
	$component = new LinkControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->url )->toBeNull();
	expect( $component->text )->toBeNull();
	expect( $component->newTab )->toBeFalse();
	expect( $component->nofollow )->toBeFalse();
	expect( $component->expanded )->toBeFalse();
} );

test( 'link control accepts custom props', function (): void {
	$component = new LinkControl(
		url: 'https://example.com',
		text: 'Example',
		newTab: true,
		nofollow: true,
		expanded: true,
	);

	expect( $component->url )->toBe( 'https://example.com' );
	expect( $component->text )->toBe( 'Example' );
	expect( $component->newTab )->toBeTrue();
	expect( $component->nofollow )->toBeTrue();
	expect( $component->expanded )->toBeTrue();
} );

test( 'link control renders', function (): void {
	$this->blade( '<x-ve-link-control />' )
		->assertSee( 'Link options', false );
} );

test( 'link control renders with label', function (): void {
	$this->blade( '<x-ve-link-control label="Link" />' )
		->assertSee( 'Link' );
} );

test( 'link control renders link options text', function (): void {
	$this->blade( '<x-ve-link-control />' )
		->assertSee( 'Link options', false );
} );
