<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Tests\TestUser;

function createTemplatePart( array $overrides = [] ): VisualEditorTemplatePart
{
	return VisualEditorTemplatePart::create( array_merge( [
		'slug'    => 'header',
		'title'   => 'Header',
		'content' => [
			'raw'    => '<!-- wp:site-title /-->',
			'blocks' => [
				[
					'name'        => 'core/site-title',
					'attributes'  => new stdClass(),
					'innerBlocks' => [],
				],
			],
		],
		'area'    => 'header',
		'theme'   => 'artisanpack-base',
	], $overrides ) );
}

function createTemplateEmbeddingPart( string $templateSlug, string $partSlug, string $theme ): VisualEditorTemplate
{
	return VisualEditorTemplate::create( [
		'slug'    => $templateSlug,
		'title'   => ucfirst( $templateSlug ),
		'content' => [
			'raw'    => sprintf( '<!-- wp:template-part {"slug":"%s","theme":"%s"} /-->', $partSlug, $theme ),
			'blocks' => [
				[
					'name'        => 'core/template-part',
					'attributes'  => [ 'slug' => $partSlug, 'theme' => $theme ],
					'innerBlocks' => [],
				],
			],
		],
		'status'  => 'publish',
		'theme'   => $theme,
		'source'  => 'theme',
		'origin'  => 'theme',
	] );
}

function actingAsTemplatePartUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Template Part Tester',
		'email'    => 'template-part+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated on index', function () {
	$this->getJson( '/visual-editor/api/template-parts' )->assertUnauthorized();
} );

it( 'returns an empty data/meta envelope when no parts exist', function () {
	actingAsTemplatePartUser();

	$this->getJson( '/visual-editor/api/template-parts' )
		->assertOk()
		->assertJsonPath( 'data', [] )
		->assertJsonPath( 'meta.total', 0 )
		->assertJsonPath( 'meta.last_page', 1 );
} );

it( 'lists template parts in the data/meta envelope', function () {
	actingAsTemplatePartUser();

	createTemplatePart( [ 'slug' => 'header', 'title' => 'Header', 'area' => 'header' ] );
	createTemplatePart( [ 'slug' => 'footer', 'title' => 'Footer', 'area' => 'footer' ] );

	$this->getJson( '/visual-editor/api/template-parts' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'meta.total', 2 )
		->assertJsonPath( 'data.0.slug', 'header' )
		->assertJsonPath( 'data.0.type', 'wp_template_part' )
		->assertJsonPath( 'data.0.area', 'header' )
		->assertJsonPath( 'data.0.title.rendered', 'Header' )
		->assertJsonPath( 'data.1.slug', 'footer' )
		->assertJsonPath( 'data.1.area', 'footer' );
} );

it( 'filters the index by slug, theme, and area', function () {
	actingAsTemplatePartUser();

	createTemplatePart( [ 'slug' => 'header', 'theme' => 'theme-a', 'area' => 'header' ] );
	createTemplatePart( [ 'slug' => 'header', 'theme' => 'theme-b', 'area' => 'header' ] );
	createTemplatePart( [ 'slug' => 'footer', 'theme' => 'theme-a', 'area' => 'footer' ] );

	$this->getJson( '/visual-editor/api/template-parts?theme=theme-a' )
		->assertOk()
		->assertJsonCount( 2, 'data' );

	$this->getJson( '/visual-editor/api/template-parts?slug=header&theme=theme-b' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.theme', 'theme-b' );

	$this->getJson( '/visual-editor/api/template-parts?area=footer' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.slug', 'footer' );
} );

it( 'returns 401 when unauthenticated on show', function () {
	$part = createTemplatePart();

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )->assertUnauthorized();
} );

it( 'returns the content envelope and rendered title on show', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertOk()
		->assertJsonPath( 'id', $part->id )
		->assertJsonPath( 'slug', 'header' )
		->assertJsonPath( 'type', 'wp_template_part' )
		->assertJsonPath( 'area', 'header' )
		->assertJsonPath( 'title.rendered', 'Header' )
		->assertJsonPath( 'content.raw', '<!-- wp:site-title /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/site-title' )
		->assertJsonPath( 'referenced_by', [] );
} );

it( 'returns 404 when the template part id does not exist', function () {
	actingAsTemplatePartUser();

	$this->getJson( '/visual-editor/api/template-parts/999' )->assertNotFound();
} );

it( 'lists referencing template slugs in referenced_by', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'slug' => 'header', 'theme' => 'artisanpack-base' ] );

	createTemplateEmbeddingPart( 'single', 'header', 'artisanpack-base' );
	createTemplateEmbeddingPart( 'page', 'header', 'artisanpack-base' );

	// Unrelated template — references a different part.
	createTemplateEmbeddingPart( 'archive', 'footer', 'artisanpack-base' );

	// Same slug, different theme — should not match.
	createTemplateEmbeddingPart( 'index', 'header', 'theme-b' );

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertOk()
		->assertJsonPath( 'referenced_by', [ 'page', 'single' ] );
} );

it( 'detects references nested inside inner blocks', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'slug' => 'header', 'theme' => 'artisanpack-base' ] );

	VisualEditorTemplate::create( [
		'slug'    => 'single',
		'title'   => 'Single',
		'content' => [
			'raw'    => '',
			'blocks' => [
				[
					'name'        => 'core/group',
					'attributes'  => [ 'tagName' => 'div' ],
					'innerBlocks' => [
						[
							'name'        => 'core/template-part',
							'attributes'  => [ 'slug' => 'header', 'theme' => 'artisanpack-base' ],
							'innerBlocks' => [],
						],
					],
				],
			],
		],
		'status'  => 'publish',
		'theme'   => 'artisanpack-base',
		'source'  => 'theme',
		'origin'  => 'theme',
	] );

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertOk()
		->assertJsonPath( 'referenced_by', [ 'single' ] );
} );

it( 'counts an untagged template-part block as a same-theme reference', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'slug' => 'header', 'theme' => 'artisanpack-base' ] );

	// Older export — `core/template-part` block references the slug but
	// omits the `theme` attribute. Since the containing template is
	// already in `artisanpack-base`, the reference resolves there.
	VisualEditorTemplate::create( [
		'slug'    => 'single',
		'title'   => 'Single',
		'content' => [
			'raw'    => '',
			'blocks' => [
				[
					'name'        => 'core/template-part',
					'attributes'  => [ 'slug' => 'header' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'  => 'publish',
		'theme'   => 'artisanpack-base',
		'source'  => 'theme',
		'origin'  => 'theme',
	] );

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertOk()
		->assertJsonPath( 'referenced_by', [ 'single' ] );
} );

it( 'deduplicates referenced_by when a template embeds the part twice', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'slug' => 'header', 'theme' => 'artisanpack-base' ] );

	VisualEditorTemplate::create( [
		'slug'    => 'single',
		'title'   => 'Single',
		'content' => [
			'raw'    => '',
			'blocks' => [
				[
					'name'        => 'core/template-part',
					'attributes'  => [ 'slug' => 'header', 'theme' => 'artisanpack-base' ],
					'innerBlocks' => [],
				],
				[
					'name'        => 'core/template-part',
					'attributes'  => [ 'slug' => 'header', 'theme' => 'artisanpack-base' ],
					'innerBlocks' => [],
				],
			],
		],
		'status'  => 'publish',
		'theme'   => 'artisanpack-base',
		'source'  => 'theme',
		'origin'  => 'theme',
	] );

	$this->getJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertOk()
		->assertJsonPath( 'referenced_by', [ 'single' ] );
} );

it( 'creates a template part with a 201 Created response for each valid area', function ( string $area ) {
	actingAsTemplatePartUser();

	$payload = [
		'slug'    => "my-{$area}",
		'title'   => ucfirst( $area ),
		'content' => [
			'raw'    => '<!-- wp:site-title /-->',
			'blocks' => [
				[
					'name'        => 'core/site-title',
					'attributes'  => new stdClass(),
					'innerBlocks' => [],
				],
			],
		],
		'area'    => $area,
		'theme'   => 'artisanpack-base',
	];

	$this->postJson( '/visual-editor/api/template-parts', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', "my-{$area}" )
		->assertJsonPath( 'area', $area )
		->assertJsonPath( 'content.raw', '<!-- wp:site-title /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/site-title' );

	expect( VisualEditorTemplatePart::where( 'slug', "my-{$area}" )->first() )
		->not->toBeNull()
		->area->toBe( $area );
} )->with( [ 'header', 'footer', 'sidebar', 'uncategorized' ] );

it( 'rejects store with an invalid area', function () {
	actingAsTemplatePartUser();

	$response = $this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'theme' => 'artisanpack-base',
		'area'  => 'banner',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'area' );

	expect( $response->json( 'errors.area.0' ) )
		->toContain( 'header' )
		->toContain( 'footer' )
		->toContain( 'sidebar' )
		->toContain( 'uncategorized' );
} );

it( 'rejects store with a missing area', function () {
	actingAsTemplatePartUser();

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'theme' => 'artisanpack-base',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'area' );
} );

it( 'rejects store when the (slug, theme) pair already exists', function () {
	actingAsTemplatePartUser();

	createTemplatePart( [ 'slug' => 'header', 'theme' => 'artisanpack-base' ] );

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'theme' => 'artisanpack-base',
		'area'  => 'header',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );
} );

it( 'allows the same slug on different themes', function () {
	actingAsTemplatePartUser();

	createTemplatePart( [ 'slug' => 'header', 'theme' => 'theme-a' ] );

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'theme' => 'theme-b',
		'area'  => 'header',
		'title' => 'Header on Theme B',
	] )->assertCreated();
} );

it( 'rejects store when the block tree is malformed', function () {
	actingAsTemplatePartUser();

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'    => 'header',
		'theme'   => 'artisanpack-base',
		'area'    => 'header',
		'content' => [
			'raw'    => '',
			'blocks' => [
				[ 'not-a-block' => true ],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content.blocks' );
} );

it( 'updates a template part with a partial payload', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'title' => 'Before' ] );

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'title' => 'After',
	] )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'After' )
		->assertJsonPath( 'slug', 'header' );

	expect( $part->fresh()->title )->toBe( 'After' );
} );

it( 'rejects update with an invalid area', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'area' => 'banner',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'area' );

	expect( $part->fresh()->area )->toBe( 'header' );
} );

it( 'updates the area with a valid enum value', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart( [ 'area' => 'uncategorized' ] );

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'area' => 'footer',
	] )
		->assertOk()
		->assertJsonPath( 'area', 'footer' );

	expect( $part->fresh()->area )->toBe( 'footer' );
} );

it( 'persists a new content envelope on update', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$newContent = [
		'raw'    => '<!-- wp:heading --><h1>Hi</h1><!-- /wp:heading -->',
		'blocks' => [
			[
				'name'        => 'core/heading',
				'attributes'  => [ 'level' => 1, 'content' => 'Hi' ],
				'innerBlocks' => [],
			],
		],
	];

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'content' => $newContent,
	] )
		->assertOk()
		->assertJsonPath( 'content.raw', $newContent['raw'] )
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Hi' );

	expect( $part->fresh()->getContentEnvelope() )->toEqual( $newContent );
} );

it( 'deletes a template part', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$this->deleteJson( "/visual-editor/api/template-parts/{$part->id}" )
		->assertNoContent();

	expect( VisualEditorTemplatePart::find( $part->id ) )->toBeNull();
} );

it( 'round-trips the B2 fixture template parts through store + show', function () {
	actingAsTemplatePartUser();

	$fixturesDir = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/template-parts';
	$files       = glob( $fixturesDir . '/*.json' );

	expect( $files )->not->toBeEmpty();

	foreach ( $files as $file ) {
		$fixture = json_decode( (string) file_get_contents( $file ), true, flags: JSON_THROW_ON_ERROR );

		$payload = [
			'slug'    => $fixture['slug'],
			'title'   => $fixture['title']['rendered'] ?? '',
			'content' => $fixture['content'] ?? [ 'raw' => '', 'blocks' => [] ],
			'area'    => $fixture['area'],
			'theme'   => $fixture['theme'],
		];

		$response = $this->postJson( '/visual-editor/api/template-parts', $payload )
			->assertCreated()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'area', $fixture['area'] )
			->assertJsonPath( 'content.raw', $fixture['content']['raw'] );

		$id = $response->json( 'id' );

		$this->getJson( "/visual-editor/api/template-parts/{$id}" )
			->assertOk()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] );
	}
} );

it( 'rejects a null title on store (column is non-nullable)', function () {
	actingAsTemplatePartUser();

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'header',
		'theme' => 'artisanpack-base',
		'area'  => 'header',
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );
} );

it( 'rejects a null title on update (column is non-nullable)', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );

	expect( $part->fresh()->title )->toBe( 'Header' );
} );

it( 'rejects a bare-list content payload on store', function () {
	actingAsTemplatePartUser();

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'    => 'header',
		'theme'   => 'artisanpack-base',
		'area'    => 'header',
		'content' => [
			[
				'name'        => 'core/paragraph',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content' );
} );

it( 'rejects a bare-list content payload on update', function () {
	actingAsTemplatePartUser();

	$part = createTemplatePart();

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'content' => [
			[
				'name'        => 'core/paragraph',
				'attributes'  => [],
				'innerBlocks' => [],
			],
		],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'content' );
} );

it( 'rejects a theme-only update that would collide with an existing (slug, theme)', function () {
	actingAsTemplatePartUser();

	// Existing record we will try to update into a colliding (slug, theme).
	$editing = createTemplatePart( [ 'slug' => 'header', 'theme' => 'theme-a' ] );

	// The collision target.
	createTemplatePart( [ 'slug' => 'header', 'theme' => 'theme-b' ] );

	$this->putJson( "/visual-editor/api/template-parts/{$editing->id}", [
		'theme' => 'theme-b',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'theme' );

	expect( $editing->fresh()->theme )->toBe( 'theme-a' );
} );

it( 'rejects unauthenticated store, update, and destroy', function () {
	$part = createTemplatePart();

	$this->postJson( '/visual-editor/api/template-parts', [
		'slug'  => 'new-header',
		'theme' => 'artisanpack-base',
		'area'  => 'header',
	] )->assertUnauthorized();

	$this->putJson( "/visual-editor/api/template-parts/{$part->id}", [
		'title' => 'Nope',
	] )->assertUnauthorized();

	$this->deleteJson( "/visual-editor/api/template-parts/{$part->id}" )->assertUnauthorized();
} );
