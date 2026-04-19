<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use Tests\Fixtures\TestDynamicBlock;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );

	$this->actor = TestUser::create( [
		'name'     => 'Preview Tester',
		'email'    => 'preview+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $this->actor );
} );

it( 'renders a registered dynamic block and returns the HTML', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'Ada' ],
	] )
		->assertOk()
		->assertJsonPath( 'name', 'tests/hello' )
		->assertJsonPath( 'html', '<p>Hello, Ada!</p>' );
} );

it( 'defaults attributes to an empty array when omitted', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name' => 'tests/hello',
	] )
		->assertOk()
		->assertJsonPath( 'html', '<p>Hello, World!</p>' );
} );

it( 'returns 404 when the block is not registered', function () {
	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'acme/missing',
		'attributes' => [],
	] )
		->assertNotFound()
		->assertJsonPath( 'error', 'block_not_registered' )
		->assertJsonPath( 'name', 'acme/missing' );
} );

it( 'returns 422 when the block name fails format validation', function () {
	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'Not A Valid Name',
		'attributes' => [],
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'name' );
} );

it( 'returns 422 when validateAttrs throws', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'greeting' => [ 'not', 'a', 'string' ] ],
	] )
		->assertStatus( 422 )
		->assertJsonPath( 'error', 'invalid_attributes' )
		->assertJsonPath( 'message', 'greeting must be a string.' );
} );

it( 'returns 403 when the block authorize callback rejects', function () {
	VisualEditor::registerDynamicBlock( TestDynamicBlock::class );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'tests/hello',
		'attributes' => [ 'name' => 'secret' ],
	] )
		->assertForbidden()
		->assertJsonPath( 'error', 'unauthorized' );
} );

it( 'renders a block registered via the closure style', function () {
	VisualEditor::registerDynamicBlock( 'acme/bold', [
		'render' => static fn ( array $attrs ): string => '<b>' . ( $attrs['text'] ?? '' ) . '</b>',
	] );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'acme/bold',
		'attributes' => [ 'text' => 'hi' ],
	] )
		->assertOk()
		->assertJsonPath( 'html', '<b>hi</b>' );
} );

it( 'renders a block that returns a Blade view', function () {
	app( 'view' )->addLocation( __DIR__ . '/../../Fixtures/views' );

	VisualEditor::registerDynamicBlock( 'acme/view', [
		'render' => static fn ( array $attrs ) => view( 'acme-view', $attrs ),
	] );

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name'       => 'acme/view',
		'attributes' => [ 'value' => 'rendered' ],
	] )
		->assertOk()
		->assertJsonPath( 'html', "<em>rendered</em>\n" );
} );

it( 'returns 401 when the request is not authenticated', function () {
	auth()->logout();

	$this->postJson( '/visual-editor/api/blocks/preview', [
		'name' => 'tests/hello',
	] )
		->assertUnauthorized();
} );

it( 'returns a generic message when the block render() throws', function () {
	VisualEditor::registerDynamicBlock( 'acme/boom', [
		'render' => static function (): string {
			throw new RuntimeException( 'secret internal path /var/www/leak' );
		},
	] );

	// Suppress the exception reporter so the test output stays clean — the
	// controller still calls report() regardless of whether it's a no-op.
	$this->withoutExceptionHandling( [] );

	$response = $this->postJson( '/visual-editor/api/blocks/preview', [
		'name' => 'acme/boom',
	] );

	$response->assertStatus( 500 )
		->assertJsonPath( 'error', 'render_failed' )
		->assertJsonPath( 'message', 'Rendering failed.' );

	expect( $response->json( 'message' ) )->not->toContain( 'secret internal path' );
} );
