<?php

/**
 * H6 PatternController integration tests — runs the visual-editor REST
 * surface against a booted cms-framework. Standalone behavior covered
 * separately in {@see PatternControllerStandaloneTest}.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Pattern tester',
		'email'    => 'patterns+' . uniqid() . '@example.com',
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

function rebuildSiteEditorResolversForPatternTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/patterns', function (): void {
	it( 'returns only the built-in `page/blank` seed pattern when no cms-framework patterns exist', function (): void {
		rebuildSiteEditorResolversForPatternTest();

		// #639 — visual-editor ships a `page/blank` starter so the
		// page-pattern-inserter modal has an entry to render out of the
		// box. Without cms-framework patterns, the index is exactly
		// that seed.
		$this->getJson( '/visual-editor/api/patterns' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'page/blank' )
			->assertJsonPath( '0.source', 'theme' )
			->assertJsonPath( '0.categories.0', 'page' )
			->assertJsonPath( '0.post_types', null );
	} );

	it( 'lists user-source patterns merged from cms-framework alongside the seed', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [ 'featured' ],
			'block_types'   => [],
			'block_content' => [ [ 'name' => 'core/buttons', 'attributes' => [], 'innerBlocks' => [] ] ],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		$response = $this->getJson( '/visual-editor/api/patterns' )
			->assertOk()
			->assertJsonCount( 2 );

		$slugs = array_column( $response->json(), 'slug' );

		expect( $slugs )->toContain( 'user/cta' )
			->and( $slugs )->toContain( 'page/blank' );
	} );

	it( 'filters by source via ?source query parameter', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		$this->getJson( '/visual-editor/api/patterns?source=user' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'user/cta' );

		// Only the built-in `page/blank` seed lives under `theme`.
		$this->getJson( '/visual-editor/api/patterns?source=theme' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'page/blank' );
	} );

	// #639 — the modal fetches patterns scoped to the current post
	// type via `?post_type=`. Contributor patterns registered with a
	// `post_types` whitelist match only when the requested slug is
	// present; unscoped patterns (post_types null) match everywhere.
	it( 'filters by ?post_type using each pattern\'s post_types scope (#639)', function (): void {
		addFilter( 'ap.visualEditor.patterns', function ( mixed $patterns ): array {
			$patterns = is_array( $patterns ) ? $patterns : [];

			$patterns['landing-hero'] = [
				'slug'       => 'landing-hero',
				'title'      => 'Landing hero',
				'source'     => 'theme',
				'synced'     => false,
				'categories' => [ 'page' ],
				'post_types' => [ 'page' ],
				'blocks'     => [],
				'raw_content' => '',
			];

			$patterns['recipe-intro'] = [
				'slug'       => 'recipe-intro',
				'title'      => 'Recipe intro',
				'source'     => 'theme',
				'synced'     => false,
				'categories' => [ 'post' ],
				'post_types' => [ 'post' ],
				'blocks'     => [],
				'raw_content' => '',
			];

			return $patterns;
		} );

		rebuildSiteEditorResolversForPatternTest();

		// `page` context: seed (unscoped) + landing-hero, but not recipe-intro.
		$page = $this->getJson( '/visual-editor/api/patterns?post_type=page' )
			->assertOk()
			->assertJsonCount( 2 );

		$pageSlugs = array_column( $page->json(), 'slug' );

		expect( $pageSlugs )->toContain( 'landing-hero' )
			->and( $pageSlugs )->toContain( 'page/blank' )
			->and( $pageSlugs )->not->toContain( 'recipe-intro' );

		// `post` context: seed (unscoped) + recipe-intro, but not landing-hero.
		$post = $this->getJson( '/visual-editor/api/patterns?post_type=post' )
			->assertOk()
			->assertJsonCount( 2 );

		$postSlugs = array_column( $post->json(), 'slug' );

		expect( $postSlugs )->toContain( 'recipe-intro' )
			->and( $postSlugs )->toContain( 'page/blank' )
			->and( $postSlugs )->not->toContain( 'landing-hero' );

		// `custom` context with no scoped patterns: seed only.
		$this->getJson( '/visual-editor/api/patterns?post_type=custom' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'page/blank' );
	} );

	it( 'filters by synced flag via ?synced query parameter', function (): void {
		BlockPattern::create( [
			'slug'          => 'synced-pattern',
			'title'         => 'Synced',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		BlockPattern::create( [
			'slug'          => 'unsynced-pattern',
			'title'         => 'Unsynced',
			'source'        => 'user',
			'synced'        => false,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		$this->getJson( '/visual-editor/api/patterns?synced=1' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'user/synced-pattern' );

		// `?synced=0` matches both the DB unsynced pattern and the
		// unsynced built-in seed.
		$unsynced = $this->getJson( '/visual-editor/api/patterns?synced=0' )
			->assertOk()
			->assertJsonCount( 2 );

		$unsyncedSlugs = array_column( $unsynced->json(), 'slug' );

		expect( $unsyncedSlugs )->toContain( 'user/unsynced-pattern' )
			->and( $unsyncedSlugs )->toContain( 'page/blank' );
	} );
} );

describe( 'GET /visual-editor/api/patterns/{slug}', function (): void {
	it( 'returns the resolved pattern with the user/ prefix in the response', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [ 'hero' ],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		$this->getJson( '/visual-editor/api/patterns/user/cta' )
			->assertOk()
			->assertJsonPath( 'slug', 'user/cta' )
			->assertJsonPath( 'title.raw', 'CTA' )
			->assertJsonPath( 'categories.0', 'hero' );
	} );

	it( 'accepts the user-facing slug (without prefix) and resolves the prefixed record', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		// URL with no prefix; controller falls back to ensureUserPrefix.
		$this->getJson( '/visual-editor/api/patterns/cta' )
			->assertOk()
			->assertJsonPath( 'slug', 'user/cta' );
	} );

	it( 'returns 404 when no pattern matches', function (): void {
		rebuildSiteEditorResolversForPatternTest();

		$this->getJson( '/visual-editor/api/patterns/missing' )->assertNotFound();
	} );

	// H7 (#432). The H6 adapter sets `id = wpId ?? slug`, and the
	// editor's `addEntities` registers `wp_block` with `key: 'id'` —
	// so after a user creates a DB-backed pattern the SPA fetches it
	// at `/patterns/{numericId}`. The route matches `{slug}`; the
	// controller resolves the numeric form by scanning for `wpId`.
	it( 'resolves a pattern by its DB id (H6 adapter id field)', function (): void {
		$pattern = BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPatternTest();

		$this->getJson( '/visual-editor/api/patterns/' . $pattern->id )
			->assertOk()
			->assertJsonPath( 'slug', 'user/cta' )
			->assertJsonPath( 'id', $pattern->id );
	} );
} );

describe( 'POST /visual-editor/api/patterns', function (): void {
	it( 'creates a user pattern and returns the prefixed slug', function (): void {
		$this->postJson( '/visual-editor/api/patterns', [
			'slug'       => 'cta',
			'title'      => 'CTA',
			'synced'     => true,
			'categories' => [ 'hero' ],
			'content'    => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/buttons', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] )
			->assertCreated()
			->assertJsonPath( 'slug', 'user/cta' )
			->assertJsonPath( 'synced', true )
			->assertJsonPath( 'content.blocks.0.name', 'core/buttons' );

		expect( BlockPattern::query()->where( 'slug', 'user/cta' )->exists() )->toBeTrue();
	} );

	it( 'returns 409 on a duplicate slug', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->postJson( '/visual-editor/api/patterns', [
			'slug'  => 'cta',
			'title' => 'Duplicate',
		] )->assertStatus( 409 );
	} );

	it( 'rejects theme as the source on store (theme patterns are file-only)', function (): void {
		$this->postJson( '/visual-editor/api/patterns', [
			'slug'   => 'rejected',
			'title'  => 'Rejected',
			'source' => 'theme',
		] )->assertStatus( 422 )->assertJsonValidationErrors( 'source' );
	} );
} );

describe( 'PUT /visual-editor/api/patterns/{slug}', function (): void {
	it( 'updates an existing user pattern and reflects the change', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/patterns/user/cta', [
			'title'      => 'CTA Renamed',
			'categories' => [ 'hero', 'pricing' ],
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'CTA Renamed' )
			->assertJsonPath( 'categories.1', 'pricing' );
	} );

	it( 'returns 404 when the slug does not match an existing user pattern', function (): void {
		$this->putJson( '/visual-editor/api/patterns/user/missing', [
			'title' => 'Nope',
		] )->assertNotFound();
	} );

	// H7 (#432). Same id-by-URL contract as the show endpoint.
	it( 'updates by DB id (H6 adapter id field)', function (): void {
		$pattern = BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/patterns/' . $pattern->id, [
			'title' => 'CTA Renamed',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'CTA Renamed' )
			->assertJsonPath( 'id', $pattern->id );
	} );
} );

describe( 'DELETE /visual-editor/api/patterns/{slug}', function (): void {
	it( 'deletes the user pattern and returns 204', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/patterns/user/cta' )->assertNoContent();

		expect( BlockPattern::query()->where( 'slug', 'user/cta' )->exists() )->toBeFalse();
	} );

	it( 'accepts the unprefixed slug and resolves the prefixed record', function (): void {
		BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/patterns/cta' )->assertNoContent();

		expect( BlockPattern::query()->where( 'slug', 'user/cta' )->exists() )->toBeFalse();
	} );

	it( 'returns 404 when no pattern matches', function (): void {
		$this->deleteJson( '/visual-editor/api/patterns/user/missing' )->assertNotFound();
	} );

	// H7 (#432). Same id-by-URL contract as show / update.
	it( 'deletes by DB id (H6 adapter id field)', function (): void {
		$pattern = BlockPattern::create( [
			'slug'          => 'cta',
			'title'         => 'CTA',
			'source'        => 'user',
			'synced'        => true,
			'categories'    => [],
			'block_types'   => [],
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/patterns/' . $pattern->id )
			->assertNoContent();

		expect( BlockPattern::query()->whereKey( $pattern->id )->exists() )->toBeFalse();
	} );
} );
