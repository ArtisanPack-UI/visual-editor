<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TemplateSwitcher;

test( 'template switcher can be instantiated with defaults', function (): void {
	$component = new TemplateSwitcher();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->id )->toBeNull();
	expect( $component->label )->toBeNull();
	expect( $component->templates )->toBe( [] );
	expect( $component->currentSlug )->toBeNull();
} );

test( 'template switcher accepts custom props', function (): void {
	$templates = [
		[ 'name' => 'Blank', 'slug' => 'blank' ],
		[ 'name' => 'Full Width', 'slug' => 'full-width' ],
	];

	$component = new TemplateSwitcher(
		id: 'tpl-switch',
		templates: $templates,
		currentSlug: 'blank',
	);

	expect( $component->uuid )->toContain( 'tpl-switch' );
	expect( $component->templates )->toHaveCount( 2 );
	expect( $component->currentSlug )->toBe( 'blank' );
} );

test( 'template switcher returns current template name', function (): void {
	$component = new TemplateSwitcher(
		templates: [
			[ 'name' => 'Blank', 'slug' => 'blank' ],
			[ 'name' => 'Full Width', 'slug' => 'full-width' ],
		],
		currentSlug: 'full-width',
	);

	expect( $component->currentTemplateName() )->toBe( 'Full Width' );
} );

test( 'template switcher returns fallback name when no selection', function (): void {
	$component = new TemplateSwitcher();

	expect( $component->currentTemplateName() )->toBeString();
	expect( $component->currentTemplateName() )->not->toBeEmpty();
} );

test( 'template switcher returns fallback name when slug not in templates', function (): void {
	$component = new TemplateSwitcher(
		templates: [ [ 'name' => 'Blank', 'slug' => 'blank' ] ],
		currentSlug: 'nonexistent',
	);

	expect( $component->currentTemplateName() )->toBeString();
} );

test( 'template switcher hasSelection returns true when slug set', function (): void {
	$component = new TemplateSwitcher( currentSlug: 'blank' );

	expect( $component->hasSelection() )->toBeTrue();
} );

test( 'template switcher hasSelection returns false when null', function (): void {
	$component = new TemplateSwitcher( currentSlug: null );

	expect( $component->hasSelection() )->toBeFalse();
} );

test( 'template switcher hasSelection returns false when empty string', function (): void {
	$component = new TemplateSwitcher( currentSlug: '' );

	expect( $component->hasSelection() )->toBeFalse();
} );

test( 'template switcher renders', function (): void {
	$view = $this->blade( '<x-ve-template-switcher />' );
	expect( $view )->not->toBeNull();
} );

test( 'template switcher renders trigger button', function (): void {
	$this->blade( '<x-ve-template-switcher />' )
		->assertSee( 'aria-haspopup="listbox"', false );
} );

test( 'template switcher renders listbox dropdown', function (): void {
	$this->blade( '<x-ve-template-switcher />' )
		->assertSee( 'role="listbox"', false );
} );
