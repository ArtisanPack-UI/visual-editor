<?php

/**
 * Custom CSS guard coverage for the site-editor save paths (M-11):
 * templates, template parts (including the theme file → DB upsert) and
 * menus (Custom CSS kept in a menu item's JSON `block_attributes`).
 *
 * Each test acts as a user without the block-CSS ability, saves a tree
 * that keeps one stored value and adds a new one, and checks that the
 * stored value survives and the new one is stripped. The model-level
 * guard (H-2) runs on the same saves, so these also lock in that both
 * layers agree.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->actingAs( TestUser::create( [
		'name'     => 'Save guard tester',
		'email'    => 'save-guard+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

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
function saveGuardBlock( string $css ): array
{
	return [
		'name'        => 'core/group',
		'attributes'  => [ 'artisanpackEffects' => [ 'opacity' => 50, 'customCss' => $css ] ],
		'innerBlocks' => [],
	];
}

/**
 * Seed a record as a trusted write, bypassing the model-level guard.
 *
 * @template T
 *
 * @param  callable(): T  $create
 *
 * @return T
 */
function saveGuardSeed( callable $create ): mixed
{
	return app( CustomCssModelGuard::class )->trusted( $create );
}

it( 'keeps stored and strips new Custom CSS on a template update', function (): void {
	$template = saveGuardSeed( fn (): Template => Template::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'single',
		'title'         => 'Single',
		'block_content' => [ saveGuardBlock( 'color: red;' ) ],
	] ) );

	$this->putJson( '/visual-editor/api/templates/single', [
		'theme'   => 'digital-shopfront',
		'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ), saveGuardBlock( 'display: none;' ) ] ],
	] )->assertOk();

	$stored = $template->fresh()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'strips Custom CSS on a template create', function (): void {
	$this->postJson( '/visual-editor/api/templates', [
		'theme'   => 'digital-shopfront',
		'slug'    => 'created',
		'title'   => 'Created',
		'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'display: none;' ) ] ],
	] )->assertCreated();

	expect( Template::query()->where( 'slug', 'created' )->sole()->block_content[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps stored and strips new Custom CSS on a template part update', function (): void {
	$part = saveGuardSeed( fn (): TemplatePart => TemplatePart::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'header',
		'title'         => 'Header',
		'area'          => 'header',
		'block_content' => [ saveGuardBlock( 'color: red;' ) ],
	] ) );

	$this->putJson( '/visual-editor/api/template-parts/header', [
		'theme'   => 'digital-shopfront',
		'area'    => 'header',
		'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ), saveGuardBlock( 'display: none;' ) ] ],
	] )->assertOk();

	$stored = $part->fresh()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps a theme file template\'s Custom CSS when upserting it to the database', function (): void {
	config()->set( 'artisanpack.visual-editor.site-editor.templates', [
		'css-file' => [
			'slug'    => 'css-file',
			'theme'   => 'digital-shopfront',
			'title'   => 'Css file',
			'source'  => 'theme',
			'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ) ] ],
		],
	] );
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->putJson( '/visual-editor/api/templates/css-file', [
		'theme'   => 'digital-shopfront',
		'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ), saveGuardBlock( 'display: none;' ) ] ],
	] )->assertOk();

	$stored = Template::query()->where( 'slug', 'css-file' )->sole()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps a theme file template part\'s Custom CSS when upserting it to the database', function (): void {
	config()->set( 'artisanpack.visual-editor.site-editor.template-parts', [
		'css-part' => [
			'slug'    => 'css-part',
			'theme'   => 'digital-shopfront',
			'title'   => 'Css part',
			'area'    => 'footer',
			'source'  => 'theme',
			'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ) ] ],
		],
	] );
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();

	$this->putJson( '/visual-editor/api/template-parts/css-part', [
		'theme'   => 'digital-shopfront',
		'area'    => 'footer',
		'content' => [ 'raw' => '', 'blocks' => [ saveGuardBlock( 'color: red;' ), saveGuardBlock( 'display: none;' ) ] ],
	] )->assertOk();

	$stored = TemplatePart::query()->where( 'slug', 'css-part' )->sole()->block_content;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps stored and strips new Custom CSS in a menu item\'s JSON block_attributes', function (): void {
	$menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );

	DB::table( 'menu_items' )->insert( [
		'menu_id'          => $menu->id,
		'type'             => MenuItem::TYPE_LINK,
		'label'            => 'Home',
		'url'              => '/',
		'position'         => 0,
		'block_attributes' => json_encode( [ 'artisanpackEffects' => [ 'customCss' => 'color: red;' ] ] ),
		'created_at'       => now(),
		'updated_at'       => now(),
	] );

	$link = static fn ( string $label, string $css ): array => [
		'name'        => 'core/navigation-link',
		'attributes'  => [ 'label' => $label, 'url' => '/' . strtolower( $label ), 'artisanpackEffects' => [ 'customCss' => $css ] ],
		'innerBlocks' => [],
	];

	$this->putJson( "/visual-editor/api/menus/{$menu->id}", [
		'content' => [ 'raw' => '', 'blocks' => [ $link( 'Home', 'color: red;' ), $link( 'About', 'display: none;' ) ] ],
	] )->assertOk();

	$rows = MenuItem::query()->where( 'menu_id', $menu->id )->orderBy( 'position' )->get();

	expect( $rows )->toHaveCount( 2 )
		->and( $rows[0]->block_attributes['artisanpackEffects']['customCss'] ?? null )->toBe( 'color: red;' )
		->and( $rows[1]->block_attributes['artisanpackEffects']['customCss'] ?? null )->toBeNull();
} );
