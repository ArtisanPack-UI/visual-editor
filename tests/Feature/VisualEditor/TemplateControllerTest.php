<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use Tests\TestUser;

function createTemplate( array $overrides = [] ): VisualEditorTemplate
{
	return VisualEditorTemplate::create( array_merge( [
		'slug'        => 'single',
		'title'       => 'Single',
		'description' => 'Default single template.',
		'content'     => [
			'raw'    => '<!-- wp:post-content /-->',
			'blocks' => [
				[
					'name'        => 'core/post-content',
					'attributes'  => new stdClass(),
					'innerBlocks' => [],
				],
			],
		],
		'status'      => 'publish',
		'theme'       => 'artisanpack-base',
		'source'      => 'theme',
		'origin'      => 'theme',
	], $overrides ) );
}

function actingAsTemplateUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Template Tester',
		'email'    => 'template+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 when unauthenticated on index', function () {
	$this->getJson( '/visual-editor/api/templates' )->assertUnauthorized();
} );

it( 'returns an empty data/meta envelope when no templates exist', function () {
	actingAsTemplateUser();

	$this->getJson( '/visual-editor/api/templates' )
		->assertOk()
		->assertJsonPath( 'data', [] )
		->assertJsonPath( 'meta.total', 0 )
		->assertJsonPath( 'meta.last_page', 1 );
} );

it( 'lists templates in the data/meta envelope', function () {
	actingAsTemplateUser();

	createTemplate( [ 'slug' => 'index', 'title' => 'Index' ] );
	createTemplate( [ 'slug' => 'single', 'title' => 'Single' ] );

	$this->getJson( '/visual-editor/api/templates' )
		->assertOk()
		->assertJsonCount( 2, 'data' )
		->assertJsonPath( 'meta.total', 2 )
		->assertJsonPath( 'data.0.slug', 'index' )
		->assertJsonPath( 'data.0.type', 'wp_template' )
		->assertJsonPath( 'data.0.title.rendered', 'Index' )
		->assertJsonPath( 'data.1.slug', 'single' );
} );

it( 'filters the index by slug and theme', function () {
	actingAsTemplateUser();

	createTemplate( [ 'slug' => 'index', 'theme' => 'theme-a' ] );
	createTemplate( [ 'slug' => 'index', 'theme' => 'theme-b' ] );
	createTemplate( [ 'slug' => 'single', 'theme' => 'theme-a' ] );

	$this->getJson( '/visual-editor/api/templates?theme=theme-a' )
		->assertOk()
		->assertJsonCount( 2, 'data' );

	$this->getJson( '/visual-editor/api/templates?slug=index&theme=theme-b' )
		->assertOk()
		->assertJsonCount( 1, 'data' )
		->assertJsonPath( 'data.0.theme', 'theme-b' );
} );

it( 'returns 401 when unauthenticated on show', function () {
	$template = createTemplate();

	$this->getJson( "/visual-editor/api/templates/{$template->id}" )->assertUnauthorized();
} );

it( 'returns the content envelope and rendered title on show', function () {
	actingAsTemplateUser();

	$template = createTemplate();

	$this->getJson( "/visual-editor/api/templates/{$template->id}" )
		->assertOk()
		->assertJsonPath( 'id', $template->id )
		->assertJsonPath( 'slug', 'single' )
		->assertJsonPath( 'type', 'wp_template' )
		->assertJsonPath( 'title.rendered', 'Single' )
		->assertJsonPath( 'content.raw', '<!-- wp:post-content /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/post-content' );
} );

it( 'returns 404 when the template id does not exist', function () {
	actingAsTemplateUser();

	$this->getJson( '/visual-editor/api/templates/999' )->assertNotFound();
} );

it( 'creates a template with a 201 Created response', function () {
	actingAsTemplateUser();

	$payload = [
		'slug'        => 'page',
		'title'       => 'Page',
		'description' => 'Fallback page template.',
		'content'     => [
			'raw'    => '<!-- wp:post-title /-->',
			'blocks' => [
				[
					'name'        => 'core/post-title',
					'attributes'  => [ 'level' => 1 ],
					'innerBlocks' => [],
				],
			],
		],
		'status'      => 'publish',
		'theme'       => 'artisanpack-base',
		'source'      => 'custom',
		'origin'      => 'custom',
	];

	$this->postJson( '/visual-editor/api/templates', $payload )
		->assertCreated()
		->assertJsonPath( 'slug', 'page' )
		->assertJsonPath( 'source', 'custom' )
		->assertJsonPath( 'content.raw', '<!-- wp:post-title /-->' )
		->assertJsonPath( 'content.blocks.0.name', 'core/post-title' );

	expect( VisualEditorTemplate::where( 'slug', 'page' )->first() )
		->not->toBeNull()
		->getBlocks()->toEqual( [
			[
				'name'        => 'core/post-title',
				'attributes'  => [ 'level' => 1 ],
				'innerBlocks' => [],
			],
		] );
} );

it( 'rejects store when the (slug, theme) pair already exists', function () {
	actingAsTemplateUser();

	createTemplate( [ 'slug' => 'index', 'theme' => 'artisanpack-base' ] );

	$this->postJson( '/visual-editor/api/templates', [
		'slug'  => 'index',
		'theme' => 'artisanpack-base',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'slug' );
} );

it( 'allows the same slug on different themes', function () {
	actingAsTemplateUser();

	createTemplate( [ 'slug' => 'index', 'theme' => 'theme-a' ] );

	$this->postJson( '/visual-editor/api/templates', [
		'slug'  => 'index',
		'theme' => 'theme-b',
		'title' => 'Index on Theme B',
	] )->assertCreated();
} );

it( 'rejects store when the block tree is malformed', function () {
	actingAsTemplateUser();

	$this->postJson( '/visual-editor/api/templates', [
		'slug'    => 'archive',
		'theme'   => 'artisanpack-base',
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

it( 'updates a template with a partial payload', function () {
	actingAsTemplateUser();

	$template = createTemplate( [ 'title' => 'Before' ] );

	$this->putJson( "/visual-editor/api/templates/{$template->id}", [
		'title' => 'After',
	] )
		->assertOk()
		->assertJsonPath( 'title.rendered', 'After' )
		->assertJsonPath( 'slug', 'single' );

	expect( $template->fresh()->title )->toBe( 'After' );
} );

it( 'persists a new content envelope on update', function () {
	actingAsTemplateUser();

	$template = createTemplate();

	$newContent = [
		'raw'    => '<!-- wp:heading --><h1>Hello</h1><!-- /wp:heading -->',
		'blocks' => [
			[
				'name'        => 'core/heading',
				'attributes'  => [ 'level' => 1, 'content' => 'Hello' ],
				'innerBlocks' => [],
			],
		],
	];

	$this->putJson( "/visual-editor/api/templates/{$template->id}", [
		'content' => $newContent,
	] )
		->assertOk()
		->assertJsonPath( 'content.raw', $newContent['raw'] )
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Hello' );

	expect( $template->fresh()->getContentEnvelope() )->toEqual( $newContent );
} );

it( 'deletes a template', function () {
	actingAsTemplateUser();

	$template = createTemplate();

	$this->deleteJson( "/visual-editor/api/templates/{$template->id}" )
		->assertNoContent();

	expect( VisualEditorTemplate::find( $template->id ) )->toBeNull();
} );

it( 'round-trips the B2 fixture templates through store + show', function () {
	actingAsTemplateUser();

	$fixturesDir = dirname( __DIR__, 2 ) . '/Fixtures/sample-content/templates';
	$files       = glob( $fixturesDir . '/*.json' );

	expect( $files )->not->toBeEmpty();

	foreach ( $files as $file ) {
		$fixture = json_decode( (string) file_get_contents( $file ), true, flags: JSON_THROW_ON_ERROR );

		$payload = [
			'slug'        => $fixture['slug'],
			'title'       => $fixture['title']['rendered'] ?? '',
			'description' => $fixture['description'] ?? null,
			'content'     => $fixture['content'] ?? [ 'raw' => '', 'blocks' => [] ],
			'status'      => $fixture['status'] ?? 'publish',
			'theme'       => $fixture['theme'],
			'source'      => $fixture['source'] ?? 'theme',
			'origin'      => $fixture['origin'] ?? 'theme',
		];

		$response = $this->postJson( '/visual-editor/api/templates', $payload )
			->assertCreated()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'content.raw', $fixture['content']['raw'] );

		$id = $response->json( 'id' );

		$this->getJson( "/visual-editor/api/templates/{$id}" )
			->assertOk()
			->assertJsonPath( 'slug', $fixture['slug'] )
			->assertJsonPath( 'content.blocks', $fixture['content']['blocks'] );
	}
} );

it( 'rejects unauthenticated store, update, and destroy', function () {
	$template = createTemplate();

	$this->postJson( '/visual-editor/api/templates', [
		'slug'  => 'new',
		'theme' => 'artisanpack-base',
	] )->assertUnauthorized();

	$this->putJson( "/visual-editor/api/templates/{$template->id}", [
		'title' => 'Nope',
	] )->assertUnauthorized();

	$this->deleteJson( "/visual-editor/api/templates/{$template->id}" )->assertUnauthorized();
} );
