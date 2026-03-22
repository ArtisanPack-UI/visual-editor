<?php

/**
 * Site Editor Layout Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\SiteEditorLayout;

test( 'site editor layout can be instantiated with defaults', function (): void {
	$component = new SiteEditorLayout();

	expect( $component->activeSection )->toBe( '' );
	expect( $component->sectionTitle )->toBe( '' );
	expect( $component->actionUrl )->toBeNull();
	expect( $component->actionLabel )->toBeNull();
	expect( $component->sidebarWidth )->toBe( '280px' );
	expect( $component->navItems )->toBe( [] );
} );

test( 'site editor layout accepts nav items', function (): void {
	$items = [
		[
			'slug'  => 'templates',
			'label' => 'Templates',
			'icon'  => '<svg></svg>',
			'url'   => '/site-editor/templates',
		],
		[
			'slug'  => 'patterns',
			'label' => 'Patterns',
			'icon'  => '<svg></svg>',
			'url'   => '/site-editor/patterns',
		],
	];

	$component = new SiteEditorLayout( navItems: $items );

	expect( $component->navItems )->toHaveCount( 2 );
	expect( $component->navItems[0]['slug'] )->toBe( 'templates' );
	expect( $component->navItems[1]['slug'] )->toBe( 'patterns' );
} );

test( 'site editor layout sets active section', function (): void {
	$component = new SiteEditorLayout( activeSection: 'templates' );

	expect( $component->activeSection )->toBe( 'templates' );
} );

test( 'site editor layout sets section title', function (): void {
	$component = new SiteEditorLayout( sectionTitle: 'Templates' );

	expect( $component->sectionTitle )->toBe( 'Templates' );
} );

test( 'site editor layout sets action button', function (): void {
	$component = new SiteEditorLayout(
		actionUrl: '/site-editor/templates/create',
		actionLabel: '+ New Template',
	);

	expect( $component->actionUrl )->toBe( '/site-editor/templates/create' );
	expect( $component->actionLabel )->toBe( '+ New Template' );
} );

test( 'site editor layout sets custom sidebar width', function (): void {
	$component = new SiteEditorLayout( sidebarWidth: '320px' );

	expect( $component->sidebarWidth )->toBe( '320px' );
} );

test( 'site editor layout hub url uses configured prefix', function (): void {
	config( [ 'artisanpack.visual-editor.site_editor.route_prefix' => 'custom-editor' ] );

	$component = new SiteEditorLayout();

	expect( $component->hubUrl() )->toContain( 'custom-editor' );
} );

test( 'site editor layout hub url uses default prefix', function (): void {
	config( [ 'artisanpack.visual-editor.site_editor.route_prefix' => 'site-editor' ] );

	$component = new SiteEditorLayout();

	expect( $component->hubUrl() )->toContain( 'site-editor' );
} );

test( 'site editor layout renders view', function (): void {
	$component = new SiteEditorLayout();

	$view = $component->render();

	expect( $view->name() )->toBe( 'visual-editor::components.site-editor-layout' );
} );

test( 'site editor layout nav items are filterable via hook', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not available.' );
	}

	addFilter( 've.site-editor.nav-items', function ( array $items ): array {
		$items[] = [
			'slug'  => 'injected',
			'label' => 'Injected Item',
			'icon'  => '<svg></svg>',
			'url'   => '/injected',
		];

		return $items;
	} );

	$component = new SiteEditorLayout( navItems: [] );

	$injected = collect( $component->navItems )->firstWhere( 'slug', 'injected' );

	expect( $injected )->not->toBeNull();
	expect( $injected['label'] )->toBe( 'Injected Item' );
} );
