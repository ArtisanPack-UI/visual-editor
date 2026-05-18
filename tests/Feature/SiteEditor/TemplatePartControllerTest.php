<?php

/**
 * H6 TemplatePartController integration tests — runs the visual-editor
 * REST surface against a booted cms-framework. Mirrors the structure of
 * {@see TemplateControllerTest} with the additional `area` field.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Site editor tester',
		'email'    => 'site-editor-parts+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

function rebuildSiteEditorResolversForPartTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/template-parts', function (): void {
	it( 'returns an empty list when no parts exist', function (): void {
		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts' )
			->assertOk()
			->assertExactJson( [] );
	} );

	it( 'lists DB-backed parts merged via cms-framework filter contributor', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [ [ 'name' => 'core/site-title', 'attributes' => [], 'innerBlocks' => [] ] ],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'header' )
			->assertJsonPath( '0.type', 'wp_template_part' )
			->assertJsonPath( '0.area', 'header' );
	} );

	it( 'filters the list by the area query param', function (): void {
		foreach ( [ 'site-header' => 'header', 'site-footer' => 'footer' ] as $slug => $area ) {
			TemplatePart::create( [
				'theme'         => 'digital-shopfront',
				'slug'          => $slug,
				'title'         => ucfirst( $area ),
				'area'          => $area,
				'status'        => 'publish',
				'is_custom'     => false,
				'block_content' => [],
				'author_id'     => null,
			] );
		}

		rebuildSiteEditorResolversForPartTest();

		// Without the `area` filter the navigator's Header / Footer /
		// Sidebar chips were cosmetic — every part showed under every
		// chip (#438).
		$this->getJson( '/visual-editor/api/template-parts?area=header' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'site-header' )
			->assertJsonPath( '0.area', 'header' );

		// No `area` param → unfiltered, both parts surface.
		$this->getJson( '/visual-editor/api/template-parts' )
			->assertOk()
			->assertJsonCount( 2 );
	} );
} );

describe( 'GET /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'returns the resolved part for an existing slug', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'footer',
			'title'         => 'Footer',
			'area'          => 'footer',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts/footer' )
			->assertOk()
			->assertJsonPath( 'slug', 'footer' )
			->assertJsonPath( 'area', 'footer' )
			->assertJsonPath( 'type', 'wp_template_part' );
	} );

	// H7 (#432). The H6 adapter sets `id = wpId ?? slug` and the
	// editor's `addEntities` registers `wp_template_part` with
	// `key: 'id'`, so the SPA fetches DB-backed parts by integer id.
	it( 'resolves a template part by its DB id (H6 adapter id field)', function (): void {
		$part = TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'footer',
			'title'         => 'Footer',
			'area'          => 'footer',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts/' . $part->id )
			->assertOk()
			->assertJsonPath( 'slug', 'footer' )
			->assertJsonPath( 'id', $part->id );
	} );

	it( 'returns 404 when the slug does not resolve', function (): void {
		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts/missing' )->assertNotFound();
	} );
} );

describe( 'POST /visual-editor/api/template-parts', function (): void {
	it( 'creates a DB-stored part and returns the resolved record', function (): void {
		$response = $this->postJson( '/visual-editor/api/template-parts', [
			'slug'    => 'sidebar',
			'title'   => 'Sidebar',
			'area'    => 'sidebar',
			'theme'   => 'digital-shopfront',
			'content' => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] );

		$response
			->assertCreated()
			->assertJsonPath( 'slug', 'sidebar' )
			->assertJsonPath( 'area', 'sidebar' )
			->assertJsonPath( 'content.blocks.0.name', 'core/navigation' );

		// The editor dereferences `entity.id` straight after create to
		// navigate to the new part. A missing / zero id sends it to
		// `/template-parts/undefined` (#438) — the response MUST carry a
		// usable id.
		$id = $response->json( 'id' );
		expect( $id )->not->toBeNull()
			->and( $id )->not->toBe( 0 );

		expect( TemplatePart::query()->where( 'slug', 'sidebar' )->exists() )->toBeTrue();
	} );

	it( 'creates the part under the active theme, ignoring a stale request theme', function (): void {
		// The site editor's mount point can carry a stale `data-theme`
		// attribute, so the create payload's `theme` may not match the
		// active theme. cms-framework's resolver only sees parts for the
		// active theme — creating under any other theme yields an
		// unresolvable part. store() must prefer the active theme (#438).
		$response = $this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'footer',
			'title' => 'Footer',
			'area'  => 'footer',
			'theme' => 'some-stale-theme',
		] );

		$response->assertCreated()->assertJsonPath( 'slug', 'footer' );

		// Persisted under the active theme (mocked to digital-shopfront),
		// not the stale value from the request body.
		expect( TemplatePart::query()->where( 'slug', 'footer' )->value( 'theme' ) )
			->toBe( 'digital-shopfront' );
	} );

	it( 'rejects unknown areas at validation', function (): void {
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'rejected',
			'title' => 'Rejected',
			'area'  => 'menu-bar',
			'theme' => 'digital-shopfront',
		] )->assertStatus( 422 )->assertJsonValidationErrors( 'area' );
	} );

	it( 'idempotently returns the existing part with 200 when (theme, slug) already exists (Keystone #55)', function (): void {
		// Gutenberg's Create Overlay action re-fires on each Click,
		// even when the nav block already has an overlay. Previously
		// the second click 409-ed, which the editor surfaced as a
		// silent failure. The controller now returns the existing
		// record with 200 so the editor's local cache picks it up
		// transparently and the block stays consistent.
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$response = $this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'header',
			'title' => 'Duplicate',
			'area'  => 'header',
			'theme' => 'digital-shopfront',
		] );

		$response->assertStatus( 200 );
		$response->assertJsonPath( 'slug', 'header' );
		// Original title preserved — the duplicate POST does NOT
		// overwrite. Updates still go through PUT.
		$response->assertJsonPath( 'title.raw', 'Header' );

		// Only one row exists — no duplicate created.
		expect( TemplatePart::query()->where( 'slug', 'header' )->count() )->toBe( 1 );
	} );

	it( 'accepts the exact payload Gutenberg posts for Create Overlay (Keystone #55)', function (): void {
		// Live capture from `[#55] StoreTemplatePartRequest failed
		// validation` — reproduces what the editor actually sends
		// when the user clicks Create Overlay on a `core/navigation`
		// block. Two non-obvious bits:
		//
		//  - `area: "navigation-overlay"` — Gutenberg's block-library
		//    extends WP core's default areas via the
		//    `block_template_part_areas` filter; the overlay flow
		//    POSTs that exact value. Without it our enum rejected.
		//  - `content` is a SERIALIZED STRING, not a `{raw, blocks}`
		//    envelope. The store path now accepts string content via
		//    the shared `ContentShapeRule`.
		//
		// No explicit `theme` field — the controller derives it from
		// the active theme.
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'    => 'navigation-overlay',
			'title'   => 'Navigation Overlay',
			'content' => '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->',
			'area'    => 'navigation-overlay',
		] )->assertStatus( 201 )->assertJsonPath( 'area', 'navigation-overlay' );
	} );

	it( 'accepts area "uncategorized" so Gutenberg\'s Create Overlay action lands (Keystone #55)', function (): void {
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'navigation-overlay-test',
			'title' => 'Overlay',
			'area'  => 'uncategorized',
			'theme' => 'digital-shopfront',
		] )->assertStatus( 201 )->assertJsonPath( 'area', 'uncategorized' );
	} );

	it( 'creates the part without an explicit theme by falling back to the active theme (Keystone #55)', function (): void {
		// Gutenberg's Create Overlay action POSTs without a `theme`
		// field — the controller derives it from the active theme.
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'no-theme-overlay',
			'title' => 'No Theme Overlay',
			'area'  => 'uncategorized',
		] )->assertStatus( 201 )->assertJsonPath( 'theme', 'digital-shopfront' );
	} );
} );

describe( 'PUT /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'updates an existing part', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/template-parts/header', [
			'theme' => 'digital-shopfront',
			'title' => 'Header Renamed',
			'area'  => 'header',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Header Renamed' );
	} );

	it( 'requires area when upserting a new part', function (): void {
		$this->putJson( '/visual-editor/api/template-parts/new-part', [
			'theme' => 'digital-shopfront',
			'title' => 'No area',
		] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'area' );
	} );

	// H7 (#432). Numeric URL parameter looks up by primary key — the
	// row already knows its theme, so the body's `theme` becomes
	// optional.
	it( 'updates by DB id without requiring a theme in the body', function (): void {
		$part = TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/template-parts/' . $part->id, [
			'title' => 'Header Renamed',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Header Renamed' )
			->assertJsonPath( 'id', $part->id );
	} );

	it( 'preserves existing block_content on a partial update that omits content.blocks', function (): void {
		$existing = [ [ 'name' => 'core/site-title', 'attributes' => [], 'innerBlocks' => [] ] ];

		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => $existing,
			'author_id'     => null,
		] );

		// Partial update — touches title, omits `content.blocks`. The
		// existing block tree must survive the write; without the guard
		// the controller would clear `block_content` to `[]`.
		$this->putJson( '/visual-editor/api/template-parts/header', [
			'theme' => 'digital-shopfront',
			'title' => 'Header Renamed',
			'area'  => 'header',
		] )->assertOk();

		$row = TemplatePart::query()->where( 'slug', 'header' )->first();

		expect( $row )->not->toBeNull()
			->and( $row->block_content )->toBe( $existing );
	} );
} );

describe( 'DELETE /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'deletes the DB override scoped to (theme, slug) and returns 204', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/header?theme=digital-shopfront' )->assertNoContent();

		expect( TemplatePart::query()->where( 'slug', 'header' )->exists() )->toBeFalse();
	} );

	it( 'leaves overrides in other themes intact when deleting one (theme, slug)', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Shopfront Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		TemplatePart::create( [
			'theme'         => 'other-theme',
			'slug'          => 'header',
			'title'         => 'Other Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/header?theme=digital-shopfront' )->assertNoContent();

		expect( TemplatePart::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'header' )->exists() )->toBeFalse()
			->and( TemplatePart::query()->where( 'theme', 'other-theme' )->where( 'slug', 'header' )->exists() )->toBeTrue();
	} );

	// #438. When the request omits `?theme=`, the controller falls back
	// to cms-framework's active theme — the editor's revert action
	// doesn't include the theme query param.
	it( 'falls back to the active theme when ?theme= is omitted (#438)', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'area'          => 'header',
			'title'         => 'Header',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/header' )->assertNoContent();

		expect( TemplatePart::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'header' )->exists() )
			->toBeFalse();
	} );

	it( 'returns 422 when ?theme= is omitted and no active theme is bound', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		$this->deleteJson( '/visual-editor/api/template-parts/header' )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'theme' );
	} );

	it( 'returns 404 when no DB override matches the (theme, slug)', function (): void {
		$this->deleteJson( '/visual-editor/api/template-parts/missing?theme=digital-shopfront' )->assertNotFound();
	} );

	// H7 (#432). Numeric URL parameter resolves by primary key; the
	// `?theme=` query is unnecessary because the row owns its theme.
	it( 'deletes by DB id without requiring a theme query param', function (): void {
		$part = TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/' . $part->id )
			->assertNoContent();

		expect( TemplatePart::query()->whereKey( $part->id )->exists() )->toBeFalse();
	} );
} );
