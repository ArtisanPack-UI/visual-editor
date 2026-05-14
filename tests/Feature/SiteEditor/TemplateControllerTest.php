<?php

/**
 * H6 TemplateController integration tests — runs the visual-editor REST
 * surface against a booted cms-framework. Standalone-install behavior
 * (no cms-framework provider) lives in
 * {@see TemplateControllerStandaloneTest} so the two test files don't
 * fight over the same provider list.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	// Authenticate so the package's auth-gated `/visual-editor/api`
	// middleware lets the request through.
	$user = TestUser::create( [
		'name'     => 'Site editor tester',
		'email'    => 'site-editor+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	// cms-framework's TemplateResolver scopes its read query to the active
	// theme. Without an active theme it returns empty regardless of what's
	// in the DB. Mirrors cms-framework's own `TemplateFiltersTest` pattern.
	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

function rebuildSiteEditorResolversForTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/templates', function (): void {
	it( 'returns an empty list when no templates exist', function (): void {
		rebuildSiteEditorResolversForTest();

		$this->getJson( '/visual-editor/api/templates' )
			->assertOk()
			->assertExactJson( [] );
	} );

	it( 'lists DB-backed templates merged via cms-framework filter contributor', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'single',
			'title'         => 'Single',
			'description'   => 'Single post template.',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForTest();

		$this->getJson( '/visual-editor/api/templates' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'single' )
			->assertJsonPath( '0.type', 'wp_template' )
			->assertJsonPath( '0.title.rendered', 'Single' );
	} );

	it( 'filters the list by the status query param', function (): void {
		foreach ( [ 'published-tpl' => 'publish', 'draft-tpl' => 'draft' ] as $slug => $status ) {
			Template::create( [
				'theme'         => 'digital-shopfront',
				'slug'          => $slug,
				'title'         => ucfirst( $slug ),
				'description'   => '',
				'status'        => $status,
				'is_custom'     => false,
				'block_content' => [],
				'author_id'     => null,
			] );
		}

		rebuildSiteEditorResolversForTest();

		// Without the `status` filter the navigator's Published / Draft
		// chips were cosmetic — every template showed under every chip
		// (#438).
		$this->getJson( '/visual-editor/api/templates?status=draft' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'draft-tpl' )
			->assertJsonPath( '0.status', 'draft' );

		// No `status` param → unfiltered, both templates surface.
		$this->getJson( '/visual-editor/api/templates' )
			->assertOk()
			->assertJsonCount( 2 );
	} );
} );

describe( 'GET /visual-editor/api/templates/{slug}', function (): void {
	it( 'returns the resolved template for an existing slug', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'page',
			'title'         => 'Page',
			'description'   => 'Default page template.',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForTest();

		$this->getJson( '/visual-editor/api/templates/page' )
			->assertOk()
			->assertJsonPath( 'slug', 'page' )
			->assertJsonPath( 'type', 'wp_template' );
	} );

	it( 'returns 404 when the slug does not resolve', function (): void {
		rebuildSiteEditorResolversForTest();

		$this->getJson( '/visual-editor/api/templates/missing' )->assertNotFound();
	} );

	// H7 (#432). The H6 adapter sets `id = wpId ?? slug`, so for
	// DB-backed templates the SPA navigates by integer id. The route
	// param is named `{slug}`; the controller dispatches on
	// numeric-vs-non-numeric input and looks the row up by primary
	// key in the numeric branch.
	it( 'resolves a template by its DB id (H6 adapter id field)', function (): void {
		$template = Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'page',
			'title'         => 'Page',
			'description'   => 'Default page template.',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForTest();

		$this->getJson( '/visual-editor/api/templates/' . $template->id )
			->assertOk()
			->assertJsonPath( 'slug', 'page' )
			->assertJsonPath( 'id', $template->id );
	} );
} );

describe( 'POST /visual-editor/api/templates', function (): void {
	it( 'creates a DB-stored template via cms-framework and returns the resolved record', function (): void {
		// cms-framework stores the parsed block tree, not the raw markup
		// (per ResolvedEntity §raw docblock), so the response surfaces
		// `content.blocks` from the resolver and `content.raw` stays
		// empty for DB-stored entities. Asserts mirror that contract.
		$response = $this->postJson( '/visual-editor/api/templates', [
			'slug'    => 'archive',
			'title'   => 'Archive',
			'theme'   => 'digital-shopfront',
			'content' => [
				'raw'    => '',
				'blocks' => [
					[ 'name' => 'core/archives', 'attributes' => [ 'showLabel' => true ], 'innerBlocks' => [] ],
				],
			],
		] );

		$response
			->assertCreated()
			->assertJsonPath( 'slug', 'archive' )
			->assertJsonPath( 'title.raw', 'Archive' )
			->assertJsonPath( 'content.blocks.0.name', 'core/archives' );

		// The editor dereferences `entity.id` straight after create to
		// navigate to the new template. A missing / zero id sends it to
		// `/templates/undefined` (#438) — the response MUST carry a
		// usable id.
		$id = $response->json( 'id' );
		expect( $id )->not->toBeNull()
			->and( $id )->not->toBe( 0 );

		expect( Template::query()->where( 'slug', 'archive' )->exists() )->toBeTrue();
	} );

	it( 'creates the template under the active theme, ignoring a stale request theme', function (): void {
		// The site editor's mount point can carry a stale `data-theme`
		// attribute, so the create payload's `theme` may not match the
		// active theme. cms-framework's resolver only sees templates for
		// the active theme — creating under any other theme yields an
		// unresolvable template. store() must prefer the active theme (#438).
		$response = $this->postJson( '/visual-editor/api/templates', [
			'slug'  => 'search',
			'title' => 'Search',
			'theme' => 'some-stale-theme',
		] );

		$response->assertCreated()->assertJsonPath( 'slug', 'search' );

		// Persisted under the active theme (mocked to digital-shopfront),
		// not the stale value from the request body.
		expect( Template::query()->where( 'slug', 'search' )->value( 'theme' ) )
			->toBe( 'digital-shopfront' );
	} );

	it( 'returns 409 on a duplicate (theme, slug) write', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'index',
			'title'         => 'Index',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->postJson( '/visual-editor/api/templates', [
			'slug'  => 'index',
			'theme' => 'digital-shopfront',
			'title' => 'Duplicate',
		] )->assertStatus( 409 );
	} );

	it( 'rejects payloads with a bare-list content envelope', function (): void {
		$this->postJson( '/visual-editor/api/templates', [
			'slug'    => 'rejected',
			'theme'   => 'digital-shopfront',
			'content' => [ [ 'name' => 'core/paragraph' ] ],
		] )->assertStatus( 422 )->assertJsonValidationErrors( 'content' );
	} );
} );

describe( 'PUT /visual-editor/api/templates/{slug}', function (): void {
	it( 'updates an existing template and reflects the change in the resolved record', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'single',
			'title'         => 'Single',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/templates/single', [
			'theme'   => 'digital-shopfront',
			'title'   => 'Single Renamed',
			'content' => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Single Renamed' )
			->assertJsonPath( 'content.blocks.0.name', 'core/post-content' );
	} );

	it( 'upserts when the slug does not yet exist for the theme', function (): void {
		$this->putJson( '/visual-editor/api/templates/new-template', [
			'theme'   => 'digital-shopfront',
			'title'   => 'New',
			'content' => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/heading', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] )
			->assertOk()
			->assertJsonPath( 'slug', 'new-template' );

		expect( Template::query()->where( 'slug', 'new-template' )->exists() )->toBeTrue();
	} );

	it( 'returns 422 when the body slug does not match the URL slug', function (): void {
		$this->putJson( '/visual-editor/api/templates/url-slug', [
			'slug'  => 'body-slug',
			'theme' => 'digital-shopfront',
		] )->assertStatus( 422 );
	} );

	// #438. When the body omits `theme`, the controller falls back to
	// cms-framework's active theme via ThemeManager — the editor save
	// payload doesn't carry `theme` and shouldn't have to.
	it( 'falls back to the active theme when the body omits theme (#438)', function (): void {
		$this->putJson( '/visual-editor/api/templates/falls-back', [ 'title' => 'Falls back' ] )
			->assertOk()
			->assertJsonPath( 'theme', 'digital-shopfront' );

		expect( Template::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'falls-back' )->exists() )
			->toBeTrue();
	} );

	it( 'returns 422 when the body omits theme and no active theme is bound', function (): void {
		// Re-bind ThemeManager to a no-active-theme stub so the fallback
		// returns null and the 422 path actually fires.
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		$this->putJson( '/visual-editor/api/templates/single', [ 'title' => 'No theme' ] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'theme' );
	} );

	// H7 (#432). With a numeric URL parameter the controller resolves
	// the template by primary key — the row already knows its theme,
	// so the body's `theme` field becomes optional.
	it( 'updates by DB id without requiring a theme in the body', function (): void {
		$template = Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'single',
			'title'         => 'Single',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/templates/' . $template->id, [
			'title' => 'Single Renamed',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Single Renamed' )
			->assertJsonPath( 'id', $template->id );
	} );
} );

describe( 'DELETE /visual-editor/api/templates/{slug}', function (): void {
	it( 'deletes the DB override scoped to the (theme, slug) pair and returns 204', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'archive',
			'title'         => 'Archive',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/templates/archive?theme=digital-shopfront' )->assertNoContent();

		expect( Template::query()->where( 'slug', 'archive' )->exists() )->toBeFalse();
	} );

	it( 'leaves overrides in other themes intact when deleting one (theme, slug)', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'archive',
			'title'         => 'Shopfront Archive',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		Template::create( [
			'theme'         => 'other-theme',
			'slug'          => 'archive',
			'title'         => 'Other Archive',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/templates/archive?theme=digital-shopfront' )->assertNoContent();

		expect( Template::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'archive' )->exists() )->toBeFalse()
			->and( Template::query()->where( 'theme', 'other-theme' )->where( 'slug', 'archive' )->exists() )->toBeTrue();
	} );

	// #438. When the request omits `?theme=`, the controller falls back
	// to cms-framework's active theme — the editor's revert action
	// doesn't include the theme query param.
	it( 'falls back to the active theme when ?theme= is omitted (#438)', function (): void {
		Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'archive',
			'title'         => 'Archive',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/templates/archive' )->assertNoContent();

		expect( Template::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'archive' )->exists() )
			->toBeFalse();
	} );

	it( 'returns 422 when ?theme= is omitted and no active theme is bound', function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );

		$this->deleteJson( '/visual-editor/api/templates/archive' )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'theme' );
	} );

	it( 'returns 404 when no DB override matches the (theme, slug)', function (): void {
		$this->deleteJson( '/visual-editor/api/templates/missing?theme=digital-shopfront' )->assertNotFound();
	} );

	// H7 (#432). Numeric URL parameter resolves the row by primary
	// key — the row owns its theme, so `?theme=` is unnecessary in
	// this branch.
	it( 'deletes by DB id without requiring a theme query param', function (): void {
		$template = Template::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'archive',
			'title'         => 'Archive',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/templates/' . $template->id )
			->assertNoContent();

		expect( Template::query()->whereKey( $template->id )->exists() )->toBeFalse();
	} );
} );
