<?php

/**
 * Pattern preview endpoint tests (#832) — batched front-end rendering of
 * pattern card previews, the preview cache and its invalidation.
 *
 * @since 1.13.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\BlockPattern;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewCache;
use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewRenderer;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use ArtisanPackUI\VisualEditorRendererBlade\VisualEditorRendererBladeServiceProvider;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->app->register( VisualEditorRendererBladeServiceProvider::class );

	$user = TestUser::create( [
		'name'     => 'Preview tester',
		'email'    => 'pattern-preview+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
		$mock->shouldReceive( 'validateSlug' )->andReturn( false );
	} );

	config()->set( 'artisanpack.visual-editor.site-editor.patterns', [
		'hero' => [
			'slug'        => 'hero',
			'title'       => 'Hero',
			'source'      => 'theme',
			'raw_content' => '<!-- wp:paragraph --><p>Hero preview copy</p><!-- /wp:paragraph -->',
			'blocks'      => [],
		],
		'wide' => [
			'slug'           => 'wide',
			'title'          => 'Wide',
			'source'         => 'theme',
			'raw_content'    => '<!-- wp:paragraph --><p>Wide copy</p><!-- /wp:paragraph -->',
			'viewport_width' => 1400,
		],
	] );

	rebuildSiteEditorResolversForPatternPreviewTest();
} );

function rebuildSiteEditorResolversForPatternPreviewTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

function createUserPatternForPreviewTest( string $text ): BlockPattern
{
	return BlockPattern::create( [
		'slug'          => 'callout',
		'title'         => 'Callout',
		'source'        => 'user',
		'synced'        => false,
		'categories'    => [],
		'block_types'   => [],
		'block_content' => [ [
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => $text ],
			'innerBlocks' => [],
		] ],
		'author_id'     => null,
	] );
}

describe( 'POST /visual-editor/api/patterns/preview', function (): void {
	it( 'renders every requested pattern in one batch with shared styles', function (): void {
		$user = createUserPatternForPreviewTest( 'User pattern copy' );

		rebuildSiteEditorResolversForPatternPreviewTest();

		$response = $this->postJson( '/visual-editor/api/patterns/preview', [
			'patterns' => [ 'hero', (string) $user->id ],
		] )->assertOk();

		expect( $response->json( 'patterns.hero.html' ) )->toContain( 'Hero preview copy' )
			->and( $response->json( 'patterns.' . $user->id . '.html' ) )->toContain( 'User pattern copy' )
			->and( $response->json( 'styles' ) )->toContain( 'data-ve-layout-baseline' );
	} );

	it( 'renders theme patterns that ship only raw content', function (): void {
		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.hero.html', fn ( string $html ): bool => str_contains( $html, '<p' ) && str_contains( $html, 'Hero preview copy' ) );
	} );

	it( 'resolves user patterns by their user-facing slug', function (): void {
		createUserPatternForPreviewTest( 'Slug lookup copy' );

		rebuildSiteEditorResolversForPatternPreviewTest();

		$response = $this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'callout' ] ] )
			->assertOk();

		expect( $response->json( 'patterns.callout.html' ) )->toContain( 'Slug lookup copy' );
	} );

	it( 'never ships script tags in the shared styles', function (): void {
		$response = $this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertOk();

		expect( $response->json( 'styles' ) )->not->toContain( '<script' );
	} );

	it( 'reports unknown patterns per pattern and still renders the rest', function (): void {
		$response = $this->postJson( '/visual-editor/api/patterns/preview', [
			'patterns' => [ 'hero', 'missing', '9999' ],
		] )->assertOk();

		expect( $response->json( 'patterns.missing' ) )->toBe( [ 'error' => 'not_found' ] )
			->and( $response->json( 'patterns.9999' ) )->toBe( [ 'error' => 'not_found' ] )
			->and( $response->json( 'patterns.hero.html' ) )->toContain( 'Hero preview copy' );
	} );

	it( 'reports a render failure per pattern and still renders the rest', function (): void {
		addFilter( 'ap.visualEditor.patternRender', function ( string $raw, string $slug ): string {
			if ( 'wide' === $slug ) {
				throw new RuntimeException( 'Render exploded.' );
			}

			return $raw;
		}, 10, 2 );

		$response = $this->postJson( '/visual-editor/api/patterns/preview', [
			'patterns' => [ 'wide', 'hero' ],
		] )->assertOk();

		expect( $response->json( 'patterns.wide' ) )->toBe( [ 'error' => 'render_failed' ] )
			->and( $response->json( 'patterns.hero.html' ) )->toContain( 'Hero preview copy' );

		removeAllFilters( 'ap.visualEditor.patternRender' );
	} );

	it( 'serves a cached render on the next request', function (): void {
		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )->assertOk();

		Cache::put( heroPreviewCacheKey(), '<p>from cache</p>', 60 );

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.hero.html', '<p>from cache</p>' );
	} );

	it( 'returns an object keyed by id for a single numeric id', function (): void {
		$user = createUserPatternForPreviewTest( 'Numeric id copy' );

		rebuildSiteEditorResolversForPatternPreviewTest();

		$content = $this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ (string) $user->id ] ] )
			->assertOk()
			->getContent();

		expect( $content )->toContain( '"patterns":{"' . $user->id . '":' );
	} );

	it( 'keeps cached renders per user', function (): void {
		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )->assertOk();

		Cache::put( heroPreviewCacheKey(), '<p>first user only</p>', 60 );

		$this->actingAs( TestUser::create( [
			'name'     => 'Second user',
			'email'    => 'pattern-preview-second+' . uniqid() . '@example.com',
			'password' => bcrypt( 'secret' ),
		] ) );

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.hero.html', fn ( string $html ): bool => str_contains( $html, 'Hero preview copy' ) );
	} );

	it( 'reports every pattern as unavailable without the Blade renderer', function (): void {
		$this->mock( PatternPreviewRenderer::class, function ( $mock ): void {
			$mock->shouldReceive( 'available' )->andReturn( false );
			$mock->shouldNotReceive( 'render' );
		} );

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero', 'wide' ] ] )
			->assertOk()
			->assertJsonPath( 'styles', '' )
			->assertJsonPath( 'patterns.hero', [ 'error' => 'renderer_unavailable' ] )
			->assertJsonPath( 'patterns.wide', [ 'error' => 'renderer_unavailable' ] );
	} );

	it( 'rejects guests', function (): void {
		auth()->logout();

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertUnauthorized();
	} );

	it( 'validates the batch', function ( array $payload ): void {
		$this->postJson( '/visual-editor/api/patterns/preview', $payload )
			->assertUnprocessable()
			->assertJsonValidationErrors( 'patterns' );
	} )->with( [
		'missing'   => [ [] ],
		'empty'     => [ [ 'patterns' => [] ] ],
		'not array' => [ [ 'patterns' => 'hero' ] ],
		'too many'  => [ [ 'patterns' => array_map( static fn ( int $i ): string => 'p' . $i, range( 1, 25 ) ) ] ],
	] );

	it( 'does not render markup sent in the request', function (): void {
		$response = $this->postJson( '/visual-editor/api/patterns/preview', [
			'patterns' => [ '<!-- wp:paragraph --><p>Injected</p><!-- /wp:paragraph -->' ],
			'markup'   => '<p>Injected</p>',
		] )->assertOk();

		expect( (string) $response->getContent() )->not->toContain( 'Injected</p>"' )
			->and( array_values( $response->json( 'patterns' ) ) )->toBe( [ [ 'error' => 'not_found' ] ] );
	} );
} );

describe( 'pattern preview cache invalidation', function (): void {
	it( 'drops the cached preview when a pattern is updated', function (): void {
		$user = createUserPatternForPreviewTest( 'Before update' );

		rebuildSiteEditorResolversForPatternPreviewTest();

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ (string) $user->id ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.' . $user->id . '.html', fn ( string $html ): bool => str_contains( $html, 'Before update' ) );

		$this->putJson( '/visual-editor/api/patterns/' . $user->id, [
			'content' => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'After update' ], 'innerBlocks' => [] ] ],
			],
		] )->assertOk();

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ (string) $user->id ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.' . $user->id . '.html', fn ( string $html ): bool => str_contains( $html, 'After update' ) );
	} );

	it( 'bumps the pattern version on update and delete', function (): void {
		$user = createUserPatternForPreviewTest( 'Versioned' );

		rebuildSiteEditorResolversForPatternPreviewTest();

		$keyBefore = previewCacheKeyFor( (string) $user->slug );

		$this->putJson( '/visual-editor/api/patterns/' . $user->id, [ 'title' => 'Renamed' ] )->assertOk();

		$keyAfterUpdate = previewCacheKeyFor( (string) $user->slug );

		$this->deleteJson( '/visual-editor/api/patterns/' . $user->id )->assertNoContent();

		expect( $keyAfterUpdate )->not->toBe( $keyBefore )
			->and( previewCacheKeyFor( (string) $user->slug ) )->not->toBe( $keyAfterUpdate );
	} );

	it( 'invalidates every preview when global styles change', function (): void {
		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )->assertOk();

		Cache::put( heroPreviewCacheKey(), '<p>stale</p>', 60 );

		$this->putJson( '/visual-editor/api/global-styles/__base__', [
			'theme'    => 'digital-shopfront',
			'settings' => [],
			'styles'   => [ 'typography' => [ 'fontSize' => '18px' ] ],
		] )->assertOk();

		$this->postJson( '/visual-editor/api/patterns/preview', [ 'patterns' => [ 'hero' ] ] )
			->assertOk()
			->assertJsonPath( 'patterns.hero.html', fn ( string $html ): bool => str_contains( $html, 'Hero preview copy' ) );
	} );

	it( 'changes the key when the theme or content changes', function (): void {
		$cache   = app( PatternPreviewCache::class );
		$pattern = app( PatternResolver::class )->find( 'hero' );

		expect( $cache->key( $pattern, 'a', 'theme-one' ) )->not->toBe( $cache->key( $pattern, 'b', 'theme-one' ) )
			->and( $cache->key( $pattern, 'a', 'theme-one' ) )->not->toBe( $cache->key( $pattern, 'a', 'theme-two' ) )
			->and( $cache->key( $pattern, 'a', 'theme-one' ) )->toBe( $cache->key( $pattern, 'a', 'theme-one' ) );
	} );
} );

describe( 'pattern viewport width', function (): void {
	it( 'exposes viewport_width on the pattern payload', function (): void {
		$patterns = collect( $this->getJson( '/visual-editor/api/patterns' )->assertOk()->json() )->keyBy( 'slug' );

		expect( $patterns['wide']['viewport_width'] )->toBe( 1400 )
			->and( $patterns['hero']['viewport_width'] )->toBeNull();
	} );

	it( 'normalizes viewport widths from filter contributors', function ( mixed $raw, ?int $expected ): void {
		$pattern = ResolvedPattern::fromArray( [
			'slug'           => 'x',
			'title'          => 'X',
			'viewport_width' => $raw,
		] );

		expect( $pattern->viewportWidth )->toBe( $expected );
	} )->with( [
		'int'          => [ 1400, 1400 ],
		'digit string' => [ '960', 960 ],
		'missing'      => [ null, null ],
		'zero'         => [ 0, null ],
		'negative'     => [ -10, null ],
		'non-numeric'  => [ 'wide', null ],
		'too narrow'   => [ 100, ResolvedPattern::MIN_VIEWPORT_WIDTH ],
		'too wide'     => [ 9000, ResolvedPattern::MAX_VIEWPORT_WIDTH ],
	] );
} );

/**
 * Cache key of the `hero` preview as the renderer computes it.
 */
function heroPreviewCacheKey(): string
{
	return previewCacheKeyFor( 'hero' );
}

/**
 * Cache key of a pattern's preview as the renderer computes it — the
 * filtered raw markup plus the block tree, under the mocked active theme.
 */
function previewCacheKeyFor( string $slug ): string
{
	$pattern = app( PatternResolver::class )->find( $slug )
		?? new ResolvedPattern( $slug, $slug, '', [], 'user', false, [], [], null );

	return app( PatternPreviewCache::class )->key(
		$pattern,
		$pattern->rawContent . "\n" . json_encode( $pattern->blocks ),
		'digital-shopfront',
	);
}
