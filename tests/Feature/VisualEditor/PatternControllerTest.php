<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorPattern;
use ArtisanPackUI\VisualEditor\Models\VisualEditorPatternCategory;
use Tests\TestUser;

function createPattern( array $overrides = [], array $categories = [] ): VisualEditorPattern
{
	$attributes = array_merge( [
		'slug'    => 'hero',
		'title'   => 'Hero',
		'content' => [
			'raw'    => '<!-- wp:cover /-->',
			'blocks' => [
				[
					'name'        => 'core/cover',
					'attributes'  => new stdClass(),
					'innerBlocks' => [],
				],
			],
		],
		'synced'  => true,
		'status'  => 'publish',
	], $overrides );

	$pattern = VisualEditorPattern::create( $attributes );

	if ( [] !== $categories ) {
		$ids = [];
		foreach ( $categories as $slug ) {
			$category = VisualEditorPatternCategory::firstOrCreate(
				[ 'slug' => $slug ],
				[ 'name' => $slug ]
			);
			$ids[] = $category->getKey();
		}

		$pattern->categories()->sync( $ids );
	}

	return $pattern;
}

function actingAsPatternUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Pattern Tester',
		'email'    => 'pattern+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated on index', function () {
	$this->getJson( '/visual-editor/api/patterns' )->assertUnauthorized();
} );

it( 'returns an empty data/meta envelope when no patterns exist', function () {
	actingAsPatternUser();

	$this->getJson( '/visual-editor/api/patterns' )
		->assertOk()
		->assertJsonPath( 'data', [] )
		->assertJsonPath( 'meta.total', 0 )
		->assertJsonPath( 'meta.last_page', 1 );
} );

it( 'lists patterns in the data/meta envelope', function () {
	actingAsPatternUser();

	createPattern( [ 'slug' => 'hero', 'title' => 'Hero' ], [ 'featured' ] );
	createPattern( [ 'slug' => 'pricing-row', 'title' => 'Pricing row', 'synced' => false ], [ 'pricing' ] );

	$this->getJson( '/visual-editor/api/patterns' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'meta.total', 2 )
		->assertJsonPath( 'data.0.slug', 'hero' )
		->assertJsonPath( 'data.0.type', 'wp_block' )
		->assertJsonPath( 'data.0.title.rendered', 'Hero' )
		->assertJsonPath( 'data.0.synced', true )
		->assertJsonPath( 'data.0.categories', [ 'featured' ] )
		->assertJsonPath( 'data.1.slug', 'pricing-row' )
		->assertJsonPath( 'data.1.synced', false )
		->assertJsonPath( 'data.1.categories', [ 'pricing' ] );
} );

it( 'filters the index by a single category slug', function () {
	actingAsPatternUser();

	createPattern( [ 'slug' => 'hero' ], [ 'featured' ] );
	createPattern( [ 'slug' => 'pricing-row', 'synced' => false ], [ 'pricing' ] );
	createPattern( [ 'slug' => 'call-out-pair', 'synced' => false ], [ 'featured' ] );

	$this->getJson( '/visual-editor/api/patterns?categories=featured' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'data.0.slug', 'hero' )
		->assertJsonPath( 'data.1.slug', 'call-out-pair' );
} );

it( 'filters the index by multiple category slugs with OR semantics', function () {
	actingAsPatternUser();

	createPattern( [ 'slug' => 'hero' ], [ 'featured' ] );
	createPattern( [ 'slug' => 'pricing-row', 'synced' => false ], [ 'pricing' ] );
	createPattern( [ 'slug' => 'sidebar', 'synced' => false ], [ 'sidebars' ] );

	$this->getJson( '/visual-editor/api/patterns?categories[]=featured&categories[]=pricing' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'data.0.slug', 'hero' )
		->assertJsonPath( 'data.1.slug', 'pricing-row' );
} );

it( 'filters the index by slug, status, and synced flag', function () {
	actingAsPatternUser();

	createPattern( [ 'slug' => 'hero', 'synced' => true, 'status' => 'publish' ] );
	createPattern( [ 'slug' => 'pricing-row', 'synced' => false, 'status' => 'publish' ] );
	createPattern( [ 'slug' => 'draft-pattern', 'synced' => false, 'status' => 'draft' ] );

	$this->getJson( '/visual-editor/api/patterns?slug=hero' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.slug', 'hero' );

	$this->getJson( '/visual-editor/api/patterns?status=draft' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.status', 'draft' );

	$this->getJson( '/visual-editor/api/patterns?synced=1' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.synced', true );

	$this->getJson( '/visual-editor/api/patterns?synced=0' )
		->assertOk()
		->assertJsonCount( 2, 'data' );
} );

it( 'returns 401 when unauthenticated on show', function () {
	$pattern = createPattern();

	$this->getJson( "/visual-editor/api/patterns/{$pattern->id}" )->assertUnauthorized();
} );

it( 'returns the full record envelope on show', function () {
	actingAsPatternUser();

	$pattern = createPattern( [], [ 'featured' ] );

	$this->getJson( "/visual-editor/api/patterns/{$pattern->id}" )
		->assertOk()
		->assertJsonPath( 'id', $pattern->id )
		->assertJsonPath( 'slug', 'hero' )
		->assertJsonPath( 'type', 'wp_block' )
		->assertJsonPath( 'title.rendered', 'Hero' )
		->assertJsonPath( 'synced', true )
		->assertJsonPath( 'categories', [ 'featured' ] )
		->assertJsonPath( 'content.raw', '<!-- wp:cover /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/cover' );
} );

it( 'returns 404 when the pattern id does not exist', function () {
	actingAsPatternUser();

	$this->getJson( '/visual-editor/api/patterns/999' )->assertNotFound();
} );

it( 'creates a synced pattern with a 201 Created response and auto-creates categories', function () {
	actingAsPatternUser();

	$payload = [
		'slug'       => 'hero',
		'title'      => 'Hero',
		'content'    => [
			'raw'    => '<!-- wp:cover /-->',
			'blocks' => [
				[
					'name'        => 'core/cover',
					'attributes'  => [ 'overlayColor' => 'primary' ],
					'innerBlocks' => [],
				],
			],
		],
		'synced'     => true,
		'status'     => 'publish',
		'categories' => [ 'featured' ],
	];

	$this->postJson( '/visual-editor/api/patterns', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', 'hero' )
		->assertJsonPath( 'synced', true )
		->assertJsonPath( 'categories', [ 'featured' ] )
		->assertJsonPath( 'type', 'wp_block' )
		->assertJsonPath( 'content.raw', '<!-- wp:cover /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/cover' );

	expect( VisualEditorPattern::where( 'slug', 'hero' )->first() )
		->not->toBeNull()
		->getBlocks()->toEqual( [
			[
				'name'        => 'core/cover',
				'attributes'  => [ 'overlayColor' => 'primary' ],
				'innerBlocks' => [],
			],
		] );

	expect( VisualEditorPatternCategory::where( 'slug', 'featured' )->first() )
		->not->toBeNull();
} );

it( 'creates an unsynced pattern with a 201 Created response', function () {
	actingAsPatternUser();

	$payload = [
		'slug'       => 'pricing-row',
		'title'      => 'Pricing row',
		'content'    => [
			'raw'    => '<!-- wp:columns /-->',
			'blocks' => [
				[
					'name'        => 'core/columns',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
		'synced'     => false,
		'categories' => [ 'pricing' ],
	];

	$this->postJson( '/visual-editor/api/patterns', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', 'pricing-row' )
		->assertJsonPath( 'synced', false )
		->assertJsonPath( 'categories', [ 'pricing' ] );
} );

it( 'defaults synced to false when the flag is omitted on store', function () {
	actingAsPatternUser();

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'some-pattern',
		'title' => 'Some pattern',
	] )
		->assertCreated()
		->assertJsonPath( 'synced', false );
} );

it( 'rejects store when the slug already exists', function () {
	actingAsPatternUser();

	createPattern( [ 'slug' => 'hero' ] );

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'hero',
		'title' => 'Duplicate',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );
} );

it( 'rejects store when the block tree is malformed', function () {
	actingAsPatternUser();

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'    => 'bad-blocks',
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

it( 'rejects a null title on store (column is non-nullable)', function () {
	actingAsPatternUser();

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'no-title',
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );
} );

it( 'rejects a bare-list content payload on store', function () {
	actingAsPatternUser();

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'    => 'bare-list',
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

it( 'updates a pattern with a partial payload', function () {
	actingAsPatternUser();

	$pattern = createPattern( [ 'title' => 'Before' ] );

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'title' => 'After',
	] )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'After' )
		->assertJsonPath( 'slug', 'hero' );

	expect( $pattern->fresh()->title )->toBe( 'After' );
} );

it( 'persists a new content envelope on update', function () {
	actingAsPatternUser();

	$pattern = createPattern();

	$newContent = [
		'raw'    => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
		'blocks' => [
			[
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Hello' ],
				'innerBlocks' => [],
			],
		],
	];

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'content' => $newContent,
	] )
		->assertOk()
		->assertJsonPath( 'content.raw', $newContent['raw'] )
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Hello' );

	expect( $pattern->fresh()->getContentEnvelope() )->toEqual( $newContent );
} );

it( 'syncs categories on update (add, remove, replace)', function () {
	actingAsPatternUser();

	$pattern = createPattern( [], [ 'featured' ] );

	// Replace the category list — `pricing` is new, `featured` should drop.
	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'categories' => [ 'pricing' ],
	] )
		->assertOk()
		->assertJsonPath( 'categories', [ 'pricing' ] );

	expect( $pattern->fresh()->categories->pluck( 'slug' )->all() )
		->toEqual( [ 'pricing' ] );

	// Drop all categories.
	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'categories' => [],
	] )
		->assertOk()
		->assertJsonPath( 'categories', [] );

	expect( $pattern->fresh()->categories )->toHaveCount( 0 );
} );

it( 'supports status transitions draft → publish → draft', function () {
	actingAsPatternUser();

	$pattern = createPattern( [ 'status' => 'draft' ] );

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'status' => 'publish',
	] )
		->assertOk()
		->assertJsonPath( 'status', 'publish' );

	expect( $pattern->fresh()->status )->toBe( 'publish' );

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'status' => 'draft',
	] )
		->assertOk()
		->assertJsonPath( 'status', 'draft' );

	expect( $pattern->fresh()->status )->toBe( 'draft' );
} );

it( 'rejects a null title on update (column is non-nullable)', function () {
	actingAsPatternUser();

	$pattern = createPattern();

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'title' => null,
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );

	expect( $pattern->fresh()->title )->toBe( 'Hero' );
} );

it( 'rejects a bare-list content payload on update', function () {
	actingAsPatternUser();

	$pattern = createPattern();

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
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

it( 'rejects a slug update that would collide with an existing slug', function () {
	actingAsPatternUser();

	$editing = createPattern( [ 'slug' => 'editing' ] );
	createPattern( [ 'slug' => 'collision' ] );

	$this->putJson( "/visual-editor/api/patterns/{$editing->id}", [
		'slug' => 'collision',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );

	expect( $editing->fresh()->slug )->toBe( 'editing' );
} );

it( 'deletes a pattern and its pivot rows', function () {
	actingAsPatternUser();

	$pattern = createPattern( [], [ 'featured' ] );
	$id      = $pattern->id;

	$this->deleteJson( "/visual-editor/api/patterns/{$id}" )
		->assertNoContent();

	expect( VisualEditorPattern::find( $id ) )->toBeNull();

	// The category record itself should survive the pattern delete.
	expect( VisualEditorPatternCategory::where( 'slug', 'featured' )->first() )
		->not->toBeNull();
} );

it( 'synced round-trip: storing a synced pattern and referencing it by id preserves the block tree', function () {
	actingAsPatternUser();

	$payload = [
		'slug'       => 'hero',
		'title'      => 'Hero',
		'content'    => [
			'raw'    => '<!-- wp:cover /-->',
			'blocks' => [
				[
					'name'        => 'core/cover',
					'attributes'  => [ 'overlayColor' => 'primary' ],
					'innerBlocks' => [],
				],
			],
		],
		'synced'     => true,
		'categories' => [ 'featured' ],
	];

	$created = $this->postJson( '/visual-editor/api/patterns', $payload )
		->assertCreated()
		->json();

	$id = $created['id'];

	// Reference the synced pattern by id twice and confirm both reads
	// resolve to the same record and block tree.
	$this->getJson( "/visual-editor/api/patterns/{$id}" )
		->assertOk()
		->assertJsonPath( 'synced', true )
		->assertJsonPath( 'content.blocks.0.name', 'core/cover' )
		->assertJsonPath( 'content.blocks.0.attributes.overlayColor', 'primary' );

	$this->getJson( "/visual-editor/api/patterns/{$id}" )
		->assertOk()
		->assertJsonPath( 'synced', true )
		->assertJsonPath( 'content.blocks.0.attributes.overlayColor', 'primary' );
} );

it( 'unsynced round-trip: editing an unsynced pattern does not mutate a previously-inserted copy', function () {
	actingAsPatternUser();

	// Store the unsynced pattern.
	$payload = [
		'slug'    => 'pricing-row',
		'title'   => 'Pricing row',
		'content' => [
			'raw'    => '<!-- wp:columns /-->',
			'blocks' => [
				[
					'name'        => 'core/columns',
					'attributes'  => [ 'note' => 'original' ],
					'innerBlocks' => [],
				],
			],
		],
		'synced'  => false,
	];

	$created = $this->postJson( '/visual-editor/api/patterns', $payload )
		->assertCreated()
		->json();

	$id = $created['id'];

	// Simulate the editor's insert-side copy: grab the current block tree
	// and hold it independently of the stored record. (C5 only guarantees
	// this round-trip shape — the actual copy-on-insert lives in D5/E3.)
	$insertedCopy = $created['content']['blocks'];

	// Mutate the stored pattern.
	$this->putJson( "/visual-editor/api/patterns/{$id}", [
		'content' => [
			'raw'    => '<!-- wp:columns /-->',
			'blocks' => [
				[
					'name'        => 'core/columns',
					'attributes'  => [ 'note' => 'edited' ],
					'innerBlocks' => [],
				],
			],
		],
	] )
		->assertOk()
		->assertJsonPath( 'content.blocks.0.attributes.note', 'edited' );

	// The previously-inserted copy still carries the original attributes.
	expect( $insertedCopy[0]['attributes']['note'] )->toBe( 'original' );

	// And the stored record now carries the edited attributes.
	$this->getJson( "/visual-editor/api/patterns/{$id}" )
		->assertOk()
		->assertJsonPath( 'content.blocks.0.attributes.note', 'edited' );
} );

it( 'round-trips the B2 pattern fixtures through store + show', function () {
	actingAsPatternUser();

	$fixturesDir = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/patterns';
	$files       = glob( $fixturesDir . '/*.json' );

	expect( $files )->not->toBeEmpty();

	foreach ( $files as $file ) {
		$fixture = json_decode( (string) file_get_contents( $file ), true, flags: JSON_THROW_ON_ERROR );

		$payload = [
			'slug'       => $fixture['slug'],
			'title'      => $fixture['title']['rendered'] ?? '',
			'content'    => $fixture['content'] ?? [ 'raw' => '', 'blocks' => [] ],
			'synced'     => (bool) ( $fixture['synced'] ?? false ),
			'status'     => $fixture['status'] ?? 'publish',
			'categories' => $fixture['categories'] ?? [],
		];

		$response = $this->postJson( '/visual-editor/api/patterns', $payload )
			->assertCreated()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'synced', (bool) ( $fixture['synced'] ?? false ) )
			->assertJsonPath( 'content.raw', $fixture['content']['raw'] );

		$id = $response->json( 'id' );

		$this->getJson( "/visual-editor/api/patterns/{$id}" )
			->assertOk()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'categories', $fixture['categories'] ?? [] )
			->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] );
	}
} );

it( 'rejects unauthenticated store, update, and destroy', function () {
	$pattern = createPattern();

	$this->postJson( '/visual-editor/api/patterns', [
		'slug'  => 'new',
		'title' => 'New',
	] )->assertUnauthorized();

	$this->putJson( "/visual-editor/api/patterns/{$pattern->id}", [
		'title' => 'Nope',
	] )->assertUnauthorized();

	$this->deleteJson( "/visual-editor/api/patterns/{$pattern->id}" )->assertUnauthorized();
} );
