<?php

/**
 * Site Editor Hub Page Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for hub page tests.
 *
 * @return Authenticatable
 */
function createHubTestUser(): Authenticatable
{
	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Test User',
		'email'      => 'hub-test@example.com',
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Test User';
	$user->email = 'hub-test@example.com';

	return $user;
}

test( 'hub page renders successfully', function (): void {
	Livewire::test( HubPage::class )
		->assertSuccessful()
		->assertSee( __( 'visual-editor::ve.hub_welcome' ) );
} );

test( 'hub page displays all default cards', function (): void {
	Livewire::test( HubPage::class )
		->assertSee( __( 'visual-editor::ve.hub_global_styles' ) )
		->assertSee( __( 'visual-editor::ve.hub_templates' ) )
		->assertSee( __( 'visual-editor::ve.hub_template_parts' ) )
		->assertSee( __( 'visual-editor::ve.hub_patterns' ) );
} );

test( 'hub page displays card descriptions', function (): void {
	Livewire::test( HubPage::class )
		->assertSee( __( 'visual-editor::ve.hub_global_styles_description' ) )
		->assertSee( __( 'visual-editor::ve.hub_templates_description' ) )
		->assertSee( __( 'visual-editor::ve.hub_template_parts_description' ) )
		->assertSee( __( 'visual-editor::ve.hub_patterns_description' ) );
} );

test( 'hub page getCards returns four default cards', function (): void {
	$component = new HubPage();
	$cards     = $component->getCards();

	expect( $cards )->toHaveCount( 4 );
	expect( array_column( $cards, 'slug' ) )->toBe( [
		'global-styles',
		'templates',
		'template-parts',
		'patterns',
	] );
} );

test( 'hub page cards include template counts', function (): void {
	$component = new HubPage();
	$cards     = $component->getCards();

	$templateCard = collect( $cards )->firstWhere( 'slug', 'templates' );

	expect( $templateCard['count'] )->toBeInt();
	expect( $templateCard['count'] )->toBeGreaterThanOrEqual( 0 );
} );

test( 'hub page cards include template part counts', function (): void {
	$component = new HubPage();
	$cards     = $component->getCards();

	$partsCard = collect( $cards )->firstWhere( 'slug', 'template-parts' );

	expect( $partsCard['count'] )->toBeInt();
	expect( $partsCard['count'] )->toBeGreaterThanOrEqual( 0 );
} );

test( 'hub page cards use configured route prefix for urls', function (): void {
	config( [ 'artisanpack.visual-editor.site_editor.route_prefix' => 'custom-editor' ] );

	$component = new HubPage();
	$cards     = $component->getCards();

	foreach ( $cards as $card ) {
		expect( $card['url'] )->toContain( 'custom-editor' );
	}
} );

test( 'hub page cards are filterable via hook', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'Hooks package not available.' );
	}

	$callback = function ( array $cards ): array {
		$cards[] = [
			'slug'        => 'custom-section',
			'label'       => 'Custom Section',
			'description' => 'A custom hub card.',
			'icon'        => '<svg></svg>',
			'url'         => '/custom',
			'count'       => null,
		];

		return $cards;
	};

	addFilter( 've.hub.cards', $callback );

	$component = new HubPage();
	$cards     = $component->getCards();

	$customCard = collect( $cards )->firstWhere( 'slug', 'custom-section' );

	expect( $customCard )->not->toBeNull();
	expect( $customCard['label'] )->toBe( 'Custom Section' );

	removeFilter( 've.hub.cards', $callback );
} );

test( 'site editor route is accessible with permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createHubTestUser();

	$this->actingAs( $user )
		->get( '/site-editor' )
		->assertSuccessful();
} );

test( 'site editor route is forbidden without permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createHubTestUser();

	$this->actingAs( $user )
		->get( '/site-editor' )
		->assertForbidden();
} );

test( 'site editor route has correct name', function (): void {
	expect( route( 'visual-editor.site-editor' ) )->toContain( '/site-editor' );
} );
