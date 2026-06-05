<?php

declare( strict_types=1 );

/**
 * Feature tests for the G3 PageController — WP-shape REST surface for
 * cms-framework's `Page`. Exercised against `TestBlockContentPageModel`
 * which uses `HasBlockContent` with a custom `body` block-content
 * column to prove the controller doesn't hard-code the column name.
 *
 * @since 1.0.0
 */

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentPageModel;
use Tests\Fixtures\TestG3Policy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'pages' => TestBlockContentPageModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	Gate::policy( TestBlockContentPageModel::class, TestG3Policy::class );
} );

function pageActor(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Page Tester',
		'email'    => 'page-tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function pageBlocks(): array
{
	return [
		[
			'clientId'    => 'page-cid',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Page body' ],
			'innerBlocks' => [],
		],
	];
}

it( 'returns a single page in the WP-shape envelope with type=page', function () {
	pageActor();

	$page = TestBlockContentPageModel::create( [
		'title' => 'About',
		'body'  => pageBlocks(),
	] );

	$this->getJson( "/visual-editor/api/pages/{$page->id}" )
		->assertOk()
		->assertJsonPath( 'id', $page->id )
		->assertJsonPath( 'title.rendered', 'About' )
		->assertJsonPath( 'type', 'page' )
		->assertJsonPath( 'content.blocks.0.name', 'core/paragraph' );
} );

it( 'omits page-only fields when the model fixture does not declare them', function () {
	pageActor();

	$page = TestBlockContentPageModel::create( [
		'title' => 'Plain',
		'body'  => [],
	] );

	$response = $this->getJson( "/visual-editor/api/pages/{$page->id}" )->assertOk();

	// TestBlockContentPageModel doesn't have parent / menu_order /
	// template columns. The resource conditionally includes them
	// based on the model's fillable + casts, so the keys are absent.
	$response->assertJsonMissing( [ 'parent' => null ] );
	$response->assertJsonMissing( [ 'menu_order' => 0 ] );
	$response->assertJsonMissing( [ 'template' => '' ] );
} );

it( 'creates a page via POST and writes the block tree to the body column', function () {
	pageActor();

	$payload = [
		'title'   => 'New Page',
		'content' => [
			'raw'    => '',
			'blocks' => pageBlocks(),
		],
	];

	$response = $this->postJson( '/visual-editor/api/pages', $payload )
		->assertCreated()
		->assertJsonPath( 'title.rendered', 'New Page' )
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Page body' );

	$id    = $response->json( 'id' );
	$saved = TestBlockContentPageModel::find( $id );

	expect( $saved )->not->toBeNull();
	// HasBlockContent was configured with `$blockContentColumn = 'body'`
	// on this fixture; the controller should have written via
	// setBlockContent() so the right column updates regardless.
	expect( $saved->getBlockContent() )->toEqual( pageBlocks() );
	expect( $saved->body )->toEqual( pageBlocks() );
} );

it( 'updates a page via PUT and round-trips through the body column', function () {
	pageActor();

	$page = TestBlockContentPageModel::create( [
		'title' => 'Original',
		'body'  => [],
	] );

	$next = [
		[
			'clientId'    => 'h1',
			'name'        => 'core/heading',
			'attributes'  => [ 'content' => 'Page Updated', 'level' => 2 ],
			'innerBlocks' => [],
		],
	];

	$this->putJson( "/visual-editor/api/pages/{$page->id}", [
		'content' => [ 'raw' => '', 'blocks' => $next ],
	] )
		->assertOk()
		->assertJsonPath( 'content.blocks.0.attributes.content', 'Page Updated' );

	expect( $page->fresh()->body )->toEqual( $next );
} );

it( 'deletes a page via DELETE and returns 204', function () {
	pageActor();

	$page = TestBlockContentPageModel::create( [
		'title' => 'Doomed',
		'body'  => [],
	] );

	$this->deleteJson( "/visual-editor/api/pages/{$page->id}" )
		->assertNoContent();

	expect( TestBlockContentPageModel::find( $page->id ) )->toBeNull();
} );
