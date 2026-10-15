<?php

/**
 * Model-level Custom CSS guard tests (H-2, F-1).
 *
 * The guard runs on every Eloquent save of a block-content column, so
 * cms-framework's own Site Editor API (which doesn't go through
 * visual-editor's controllers) can't be used to add Custom CSS.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\Effects\BlockCssAccess;
use ArtisanPackUI\VisualEditor\Effects\CustomCssGuard;
use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;
use ArtisanPackUI\VisualEditor\Models\Snippet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestJsonStringBlockContentModel;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->user = TestUser::create( [
		'name'     => 'Model guard tester',
		'email'    => 'model-guard+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

/**
 * A group block carrying the given Custom CSS.
 *
 * @return array<string, mixed>
 */
function modelGuardBlock( string $css ): array
{
	return [
		'name'        => 'core/group',
		'attributes'  => [ 'artisanpackEffects' => [ 'opacity' => 50, 'customCss' => $css ] ],
		'innerBlocks' => [],
	];
}

/**
 * Create a cms-framework record of the given kind with the given blocks.
 */
function modelGuardRecord( string $kind, array $blocks ): object
{
	return match ( $kind ) {
		'pattern' => BlockPattern::create( [
			'slug'          => 'guarded-' . uniqid(),
			'title'         => 'Guarded',
			'source'        => BlockPattern::SOURCE_USER,
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => $blocks,
		] ),
		'template' => Template::create( [
			'slug'          => 'guarded-' . uniqid(),
			'theme'         => 'digital-shopfront',
			'title'         => 'Guarded',
			'block_content' => $blocks,
		] ),
		'part' => TemplatePart::create( [
			'slug'          => 'guarded-' . uniqid(),
			'theme'         => 'digital-shopfront',
			'title'         => 'Guarded',
			'area'          => 'uncategorized',
			'block_content' => $blocks,
		] ),
	};
}

dataset( 'cms block models', [ 'pattern', 'template', 'part' ] );

it( 'strips Custom CSS from a synced pattern posted to cms-framework\'s own API', function (): void {
	config()->set( 'auth.guards.sanctum', [ 'driver' => 'session', 'provider' => 'users' ] );
	Gate::define( 'manage-content', fn (): bool => true );

	$this->actingAs( $this->user, 'sanctum' );

	$this->postJson( '/api/v1/blocks', [
		'slug'          => 'evil',
		'title'         => 'Evil',
		'block_content' => [ modelGuardBlock( 'body { display: none; }' ) ],
	] )->assertCreated();

	$stored = BlockPattern::query()->where( 'title', 'Evil' )->firstOrFail()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'strips Custom CSS on a direct create by a user without the ability', function ( string $kind ): void {
	$this->actingAs( $this->user );

	$record = modelGuardRecord( $kind, [ modelGuardBlock( 'color: red;' ) ] );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} )->with( 'cms block models' );

it( 'keeps an unchanged stored value and strips a changed one on update', function ( string $kind ): void {
	$record = modelGuardRecord( $kind, [ modelGuardBlock( 'color: red;' ), modelGuardBlock( 'color: blue;' ) ] );

	$this->actingAs( $this->user );

	$record->update( [ 'block_content' => [ modelGuardBlock( "color:   red;\n" ), modelGuardBlock( 'display: none;' ) ] ] );

	$stored = $record->fresh()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( "color:   red;\n" )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} )->with( 'cms block models' );

it( 'keeps Custom CSS for a user with the ability', function ( string $kind ): void {
	Gate::define( BlockCssAccess::ABILITY, fn (): bool => true );

	$this->actingAs( $this->user );

	$record = modelGuardRecord( $kind, [ modelGuardBlock( 'color: red;' ) ] );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' );
} )->with( 'cms block models' );

it( 'keeps Custom CSS with no authenticated user in the console, so seeding works', function ( string $kind ): void {
	expect( Auth::user() )->toBeNull()
		->and( app()->runningInConsole() )->toBeTrue();

	$record = modelGuardRecord( $kind, [ modelGuardBlock( 'color: red;' ) ] );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' );
} )->with( 'cms block models' );

it( 'keeps Custom CSS inside trusted()', function (): void {
	$this->actingAs( $this->user );

	$record = app( CustomCssModelGuard::class )->trusted( fn (): object => modelGuardRecord( 'pattern', [ modelGuardBlock( 'color: red;' ) ] ) );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' );
} );

it( 'keeps values a controller-level filter vouched for, as in a theme file upsert', function (): void {
	$this->actingAs( $this->user );

	$themeBlocks = [ modelGuardBlock( 'color: red;' ) ];
	$blocks      = app( CustomCssGuard::class )->filter( [ modelGuardBlock( 'color: red;' ), modelGuardBlock( 'color: green;' ) ], $themeBlocks );

	$record = modelGuardRecord( 'template', $blocks );
	$stored = $record->fresh()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'forgets vouched values at the request boundary', function (): void {
	$this->actingAs( $this->user );

	app( CustomCssGuard::class )->filter( [], [ modelGuardBlock( 'color: red;' ) ] );
	app()->forgetScopedInstances();

	$record = modelGuardRecord( 'pattern', [ modelGuardBlock( 'color: red;' ) ] );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'handles a block column stored as a JSON string', function (): void {
	$record = TestJsonStringBlockContentModel::create( [
		'title'   => 'Json',
		'status'  => 'published',
		'content' => json_encode( [ modelGuardBlock( 'color: red;' ) ] ),
	] );

	$this->actingAs( $this->user );

	$record->update( [ 'content' => json_encode( [ modelGuardBlock( 'color: red;' ), modelGuardBlock( 'display: none;' ) ] ) ] );

	$raw = $record->fresh()->content;

	expect( $raw )->toBeString();

	$stored = json_decode( $raw, true );

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'guards host models that use HasBlockContent', function (): void {
	$this->actingAs( $this->user );

	$record = TestBlockContentModel::create( [ 'title' => 'Host', 'status' => 'published', 'content' => [ modelGuardBlock( 'color: red;' ) ] ] );

	expect( $record->fresh()->getBlockContent()[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'guards snippets', function (): void {
	$this->actingAs( $this->user );

	$snippet = Snippet::create( [ 'slug' => 'guarded', 'title' => 'Guarded', 'blocks' => [ modelGuardBlock( 'color: red;' ) ] ] );

	expect( $snippet->fresh()->blocks[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'leaves a save that does not touch the block column alone', function (): void {
	$record = modelGuardRecord( 'pattern', [ modelGuardBlock( 'color: red;' ) ] );

	$this->actingAs( $this->user );

	$record->update( [ 'title' => 'Renamed' ] );

	expect( $record->fresh()->block_content[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' );
} );

it( 'runs the guard on cms-framework\'s Site Editor block-content filter (F-1)', function (): void {
	$this->actingAs( $this->user );

	$filtered = applyFilters(
		'ap.cmsFramework.siteEditor.blockContent',
		[ modelGuardBlock( 'color: red;' ), modelGuardBlock( 'display: none;' ) ],
		[ modelGuardBlock( 'color: red;' ) ],
		'template',
	);

	expect( $filtered[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $filtered[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'passes the cms-framework filter through for a user with the ability (F-1)', function (): void {
	Gate::define( BlockCssAccess::ABILITY, fn (): bool => true );

	$this->actingAs( $this->user );

	$blocks = [ modelGuardBlock( 'display: none;' ) ];

	expect( applyFilters( 'ap.cmsFramework.siteEditor.blockContent', $blocks, null, 'pattern' ) )->toBe( $blocks );
} );
