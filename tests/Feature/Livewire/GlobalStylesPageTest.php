<?php

/**
 * Global Styles Page Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\GlobalStylesPage;
use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesRepository;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for global styles page tests.
 *
 * @return Authenticatable
 */
function createGlobalStylesTestUser(): Authenticatable
{
	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Style Editor',
		'email'      => 'styles-test@example.com',
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Style Editor';
	$user->email = 'styles-test@example.com';

	return $user;
}

test( 'global styles page renders successfully', function (): void {
	Livewire::test( GlobalStylesPage::class )
		->assertSuccessful();
} );

test( 'global styles page loads default palette on mount', function (): void {
	$component = Livewire::test( GlobalStylesPage::class );

	$palette = $component->get( 'palette' );

	expect( $palette )->toBeArray();
	expect( $palette )->not->toBeEmpty();
} );

test( 'global styles page loads default typography on mount', function (): void {
	$component = Livewire::test( GlobalStylesPage::class );

	$typography = $component->get( 'typography' );

	expect( $typography )->toBeArray();
} );

test( 'global styles page loads default spacing on mount', function (): void {
	$component = Livewire::test( GlobalStylesPage::class );

	$spacing = $component->get( 'spacing' );

	expect( $spacing )->toBeArray();
} );

test( 'global styles page save action persists data', function (): void {
	$customPalette = [ [ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff6600' ] ];
	$typography    = [ 'fontFamilies' => [ 'heading' => 'Georgia, serif' ] ];
	$spacing       = [ 'scale' => [], 'blockGap' => 'lg' ];

	Livewire::test( GlobalStylesPage::class )
		->call( 'save', $customPalette, $typography, $spacing )
		->assertDispatched( 've-global-styles-saved' );

	$record = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();

	expect( $record )->not->toBeNull();
	expect( $record->palette )->toEqual( $customPalette );
} );

test( 'global styles page reset action restores defaults', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$repository->save( [
		'palette' => [ [ 'name' => 'Custom', 'slug' => 'custom', 'color' => '#000000' ] ],
	] );

	Livewire::test( GlobalStylesPage::class )
		->call( 'resetToDefaults' )
		->assertDispatched( 've-global-styles-reset' );

	$record          = GlobalStyle::byKey( GlobalStyle::DEFAULT_KEY )->first();
	$defaultPalette  = app( 'visual-editor.color-palette' )->toStoreFormat();

	expect( $record->palette )->toEqual( $defaultPalette );
} );

test( 'global styles page toggleHistory loads revisions', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$repository->save( [ 'palette' => [] ] );

	$component = Livewire::test( GlobalStylesPage::class )
		->call( 'toggleHistory' );

	expect( $component->get( 'showHistory' ) )->toBeTrue();
	expect( $component->get( 'revisions' ) )->toBeArray();
} );

test( 'global styles page toggleHistory closes when toggled again', function (): void {
	Livewire::test( GlobalStylesPage::class )
		->call( 'toggleHistory' )
		->call( 'toggleHistory' )
		->assertSet( 'showHistory', false );
} );

test( 'global styles page restoreRevision restores a previous state', function (): void {
	$repository = app( GlobalStylesRepository::class );

	$originalPalette = [ [ 'name' => 'Original', 'slug' => 'original', 'color' => '#aaaaaa' ] ];

	$repository->save( [ 'palette' => $originalPalette ] );

	$repository->save( [ 'palette' => [ [ 'name' => 'Changed', 'slug' => 'changed', 'color' => '#bbbbbb' ] ] ] );

	$record    = $repository->get();
	$revisions = ArtisanPackUI\VisualEditor\Models\Revision::forDocument(
		GlobalStyle::REVISION_DOCUMENT_TYPE,
		$record->id,
	)->orderByDesc( 'id' )->get();

	$revisionId = $revisions->first()->id;

	$repository->save( [ 'palette' => [ [ 'name' => 'Final', 'slug' => 'final', 'color' => '#cccccc' ] ] ] );

	Livewire::test( GlobalStylesPage::class )
		->call( 'restoreRevision', $revisionId )
		->assertDispatched( 've-global-styles-reset' );

	$restoredRecord = $repository->get();

	expect( $restoredRecord )->not->toBeNull();
	expect( $restoredRecord->palette )->toEqual( $originalPalette );
} );

test( 'global styles page restoreRevision ignores invalid revision id', function (): void {
	Livewire::test( GlobalStylesPage::class )
		->call( 'restoreRevision', 99999 )
		->assertSuccessful();
} );

test( 'global styles route is accessible with permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => true );

	$user = createGlobalStylesTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.global-styles' ) )
		->assertSuccessful();
} );

test( 'global styles route is forbidden without permission', function (): void {
	Gate::define( 'visual-editor.access-site-editor', fn () => false );

	$user = createGlobalStylesTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.global-styles' ) )
		->assertForbidden();
} );

test( 'global styles route has correct name', function (): void {
	$routeUrl = route( 'visual-editor.global-styles' );
	$prefix   = config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

	expect( $routeUrl )->toContain( '/' . $prefix . '/global-styles' );
} );
