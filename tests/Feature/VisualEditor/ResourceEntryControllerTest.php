<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;
use Tests\Fixtures\TestBlockContentPagePolicy;
use Tests\Fixtures\TestBlockContentPolicy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'packages' => TestBlockContentModel::class,
		'guides'   => TestBlockContentPageModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	Gate::policy( TestBlockContentModel::class, TestBlockContentPolicy::class );
	Gate::policy( TestBlockContentPageModel::class, TestBlockContentPagePolicy::class );
} );

function makeEntryActor(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Tester',
		'email'    => 'tester+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns 401 for an unauthenticated PUT', function (): void {
	$model = TestBlockContentModel::create( [ 'title' => 'Old', 'status' => 'published' ] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'title' => 'New' ] )
		->assertUnauthorized();
} );

it( 'returns 404 for an unregistered resource slug', function (): void {
	makeEntryActor();

	$this->putJson( '/visual-editor/api/orders/1/entry', [ 'title' => 'New' ] )->assertNotFound();
} );

it( 'saves the title and excerpt of a custom resource entry', function (): void {
	makeEntryActor();

	$model = TestBlockContentModel::create( [
		'title'   => 'Old title',
		'excerpt' => 'Old excerpt',
		'status'  => 'published',
	] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [
		'title'   => 'New title',
		'excerpt' => 'New excerpt',
	] )
		->assertOk()
		->assertJsonPath( 'id', $model->id )
		->assertJsonPath( 'resource', 'packages' )
		->assertJsonPath( 'title.raw', 'New title' )
		->assertJsonPath( 'excerpt.raw', 'New excerpt' );

	$model->refresh();

	expect( $model->title )->toBe( 'New title' )
		->and( $model->excerpt )->toBe( 'New excerpt' );
} );

it( 'accepts a cleared title', function (): void {
	makeEntryActor();

	$model = TestBlockContentModel::create( [ 'title' => 'Old title', 'status' => 'published' ] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'title' => '' ] )
		->assertOk()
		->assertJsonPath( 'title.raw', '' );

	expect( $model->refresh()->title )->toBe( '' );
} );

it( 'leaves fields that were not sent untouched', function (): void {
	makeEntryActor();

	$model = TestBlockContentModel::create( [
		'title'   => 'Old title',
		'excerpt' => 'Keep me',
		'status'  => 'published',
	] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'title' => 'New title' ] )
		->assertOk();

	expect( $model->refresh()->excerpt )->toBe( 'Keep me' );
} );

it( 'ignores fields outside title and excerpt', function (): void {
	makeEntryActor();

	$model = TestBlockContentModel::create( [ 'title' => 'Old', 'status' => 'published' ] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [
		'title'  => 'New',
		'status' => 'draft',
		'slug'   => 'hijacked',
	] )->assertOk();

	$model->refresh();

	expect( $model->status )->toBe( 'published' )
		->and( $model->slug )->toBeNull();
} );

it( 'rejects a field the record has no column for and saves nothing', function (): void {
	$user = makeEntryActor();

	$guide = TestBlockContentPageModel::create( [ 'title' => 'Old', 'author_id' => $user->id ] );

	$this->putJson( "/visual-editor/api/guides/{$guide->id}/entry", [
		'title'   => 'New',
		'excerpt' => 'No column for this',
	] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'excerpt' );

	expect( $guide->refresh()->title )->toBe( 'Old' );
} );

it( 'saves the fields a record does carry', function (): void {
	$user = makeEntryActor();

	$guide = TestBlockContentPageModel::create( [ 'title' => 'Old', 'author_id' => $user->id ] );

	$this->putJson( "/visual-editor/api/guides/{$guide->id}/entry", [ 'title' => 'New' ] )
		->assertOk()
		->assertJsonPath( 'title.raw', 'New' )
		->assertJsonMissingPath( 'excerpt' );

	expect( $guide->refresh()->title )->toBe( 'New' );
} );

it( 'validates the payload', function (): void {
	makeEntryActor();

	$model = TestBlockContentModel::create( [ 'title' => 'Old', 'status' => 'published' ] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'title' => str_repeat( 'a', 256 ) ] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'title' );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'excerpt' => [ 'not', 'a', 'string' ] ] )
		->assertUnprocessable()
		->assertJsonValidationErrors( 'excerpt' );
} );

it( 'forbids users the policy denies', function (): void {
	makeEntryActor();

	$other = TestUser::create( [
		'name'     => 'Owner',
		'email'    => 'owner+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$model = TestBlockContentModel::create( [
		'title'     => 'Old',
		'status'    => 'published',
		'author_id' => $other->id,
	] );

	$this->putJson( "/visual-editor/api/packages/{$model->id}/entry", [ 'title' => 'New' ] )
		->assertForbidden();

	expect( $model->refresh()->title )->toBe( 'Old' );
} );
