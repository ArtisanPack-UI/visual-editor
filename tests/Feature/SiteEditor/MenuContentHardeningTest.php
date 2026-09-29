<?php

/**
 * Menu content hardening — 1.12.0.
 *
 * Covers `content.blocks` validation on menu store/update, the bounds on
 * trees parsed from serialized content, the missing-`block_attributes`
 * column fallback, and the derived-slug unique-index race retry.
 *
 * @since 1.12.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor\MenuContentBlocksRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->actingAs( TestUser::create( [
		'name'     => 'Menu hardening',
		'email'    => 'menu-hardening+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );

	$this->menu = Menu::create( [ 'theme' => 'digital-shopfront', 'slug' => 'primary', 'name' => 'Primary' ] );
} );

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, mixed>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function hardeningNavBlock( array $attributes, array $innerBlocks = [] ): array
{
	return [
		'name'        => [] === $innerBlocks ? 'core/navigation-link' : 'core/navigation-submenu',
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * A chain of `$depth` nested submenus ending in a link.
 *
 * @return array<string, mixed>
 */
function hardeningNestedNav( int $depth ): array
{
	$block = hardeningNavBlock( [ 'label' => 'Leaf' ] );

	for ( $i = 1; $i < $depth; $i++ ) {
		$block = hardeningNavBlock( [ 'label' => 'Level ' . $i ], [ $block ] );
	}

	return $block;
}

/**
 * An array nested `$depth` levels deep.
 *
 * @return array<string, mixed>
 */
function hardeningDeepValue( int $depth ): array
{
	$value = [ 'leaf' => true ];

	for ( $i = 1; $i < $depth; $i++ ) {
		$value = [ 'nested' => $value ];
	}

	return $value;
}

describe( 'content.blocks validation', function (): void {
	it( 'rejects an invalid column-mapped attribute type with 422 on store and update', function ( array $attributes, string $key ): void {
		$payload = [ 'content' => [ 'blocks' => [ hardeningNavBlock( $attributes ) ] ] ];

		$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Footer', ...$payload ] )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );

		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", $payload )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );

		expect( Menu::query()->count() )->toBe( 1 )
			->and( MenuItem::query()->count() )->toBe( 0 );
	} )->with( [
		'label array'           => [ [ 'label' => [ 'x' ] ], 'label' ],
		'url number'            => [ [ 'label' => 'Home', 'url' => 42 ], 'url' ],
		'rel array'             => [ [ 'label' => 'Home', 'rel' => [ 'nofollow' ] ], 'rel' ],
		'className bool'        => [ [ 'label' => 'Home', 'className' => true ], 'className' ],
		'kind array'            => [ [ 'label' => 'Home', 'kind' => [ 'post-type' ] ], 'kind' ],
		'type number'           => [ [ 'label' => 'Home', 'type' => 3 ], 'type' ],
		'opensInNewTab string'  => [ [ 'label' => 'Home', 'opensInNewTab' => 'yes' ], 'opensInNewTab' ],
		'id array'              => [ [ 'label' => 'Home', 'id' => [ 1 ] ], 'id' ],
	] );

	it( 'accepts null / well-typed column-mapped attributes', function (): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [
				hardeningNavBlock( [ 'label' => 'Home', 'url' => '/', 'opensInNewTab' => true, 'id' => '12', 'rel' => null ] ),
				hardeningNavBlock( [ 'label' => 'Post', 'kind' => 'post-type', 'type' => 'post', 'id' => 5, 'className' => 'x' ] ),
			] ],
		] )->assertOk();

		expect( MenuItem::query()->where( 'menu_id', $this->menu->id )->count() )->toBe( 2 );
	} );

	it( 'rejects a tree deeper than the maximum depth', function (): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [ hardeningNestedNav( MenuContentBlocksRule::MAX_DEPTH + 1 ) ] ],
		] )->assertUnprocessable()->assertJsonValidationErrors( 'content.blocks' );
	} );

	it( 'accepts a tree at the maximum depth', function (): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [ hardeningNestedNav( MenuContentBlocksRule::MAX_DEPTH ) ] ],
		] )->assertOk();
	} );

	it( 'rejects a tree with more than the maximum number of blocks', function (): void {
		$blocks = array_fill( 0, MenuContentBlocksRule::MAX_NODES + 1, hardeningNavBlock( [ 'label' => 'x' ] ) );

		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [ 'content' => [ 'blocks' => $blocks ] ] )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );
	} );

	it( 'rejects non-array attributes / innerBlocks and missing names', function ( array $block ): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [ 'content' => [ 'blocks' => [ $block ] ] ] )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );
	} )->with( [
		'attributes string'  => [ [ 'name' => 'core/navigation-link', 'attributes' => 'x', 'innerBlocks' => [] ] ],
		'innerBlocks string' => [ [ 'name' => 'core/navigation-link', 'attributes' => [], 'innerBlocks' => 'x' ] ],
		'no name'            => [ [ 'attributes' => [], 'innerBlocks' => [] ] ],
	] );

	it( 'rejects extra attributes nested deeper than the cap', function (): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [
				hardeningNavBlock( [ 'label' => 'Home', 'artisanpackVisibility' => hardeningDeepValue( MenuContentBlocksRule::MAX_EXTRA_ATTRIBUTES_DEPTH ) ] ),
			] ],
		] )->assertUnprocessable()->assertJsonValidationErrors( 'content.blocks' );
	} );

	it( 'rejects extra attributes larger than the size cap', function (): void {
		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [
				hardeningNavBlock( [ 'label' => 'Home', 'metadata' => [ 'note' => str_repeat( 'a', MenuContentBlocksRule::MAX_EXTRA_ATTRIBUTES_BYTES ) ] ] ),
			] ],
		] )->assertUnprocessable()->assertJsonValidationErrors( 'content.blocks' );
	} );

	it( 'bounds a tree parsed from a serialized content string too', function (): void {
		$raw = '<!-- wp:navigation-link {"label":["x"]} /-->';

		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [ 'content' => $raw ] )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );

		$deep = str_repeat( '<!-- wp:navigation-submenu {"label":"S"} -->', 15 )
			. '<!-- wp:navigation-link {"label":"L"} /-->'
			. str_repeat( '<!-- /wp:navigation-submenu -->', 15 );

		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [ 'content' => [ 'raw' => $deep ] ] )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'content.blocks' );

		expect( MenuItem::query()->count() )->toBe( 0 );
	} );
} );

describe( 'missing block_attributes column', function (): void {
	it( 'saves navigation items without extension attributes instead of failing', function (): void {
		Schema::table( 'menu_items', function ( Blueprint $table ): void {
			$table->dropColumn( 'block_attributes' );
		} );

		$this->putJson( "/visual-editor/api/menus/{$this->menu->id}", [
			'content' => [ 'blocks' => [
				hardeningNavBlock( [ 'label' => 'Home', 'url' => '/', 'artisanpackVisibility' => [ 'screenSize' => [ 'direction' => 'hide' ] ] ] ),
			] ],
		] )->assertOk()->assertJsonPath( 'content.blocks.0.attributes.label', 'Home' );

		$row = DB::table( 'menu_items' )->where( 'menu_id', $this->menu->id )->sole();

		expect( $row->label )->toBe( 'Home' )
			->and( property_exists( $row, 'block_attributes' ) )->toBeFalse();
	} );
} );

describe( 'derived slug race', function (): void {
	it( 'retries a derived slug that loses the unique-index race instead of answering 409', function (): void {
		$raced = false;

		// Simulate a concurrent create committing the same derived slug
		// between our probe and our insert, exactly once.
		Menu::creating( function ( Menu $menu ) use ( &$raced ): void {
			if ( $raced ) {
				return;
			}

			$raced = true;

			DB::table( 'menus' )->insert( [
				'theme'      => $menu->theme,
				'slug'       => $menu->slug,
				'name'       => 'Concurrent',
				'created_at' => now(),
				'updated_at' => now(),
			] );
		} );

		$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Footer' ] )
			->assertCreated()
			->assertJsonPath( 'name', 'Footer' );

		expect( $raced )->toBeTrue();
	} );

	it( 'still answers 409 when a client-supplied slug collides', function (): void {
		$this->postJson( '/visual-editor/api/menus', [ 'title' => 'Again', 'slug' => 'primary' ] )
			->assertStatus( 409 );
	} );
} );
