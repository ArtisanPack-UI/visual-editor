<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\ContentRevision;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user    = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'Test Content',
		'slug'      => 'test-content',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
} );

test( 'content revision has correct table name', function (): void {
	$revision = new ContentRevision();

	expect( $revision->getTable() )->toBe( 've_content_revisions' );
} );

test( 'content revision has correct fillable attributes', function (): void {
	$revision = new ContentRevision();

	expect( $revision->getFillable() )->toContain( 'content_id' )
		->and( $revision->getFillable() )->toContain( 'user_id' )
		->and( $revision->getFillable() )->toContain( 'type' )
		->and( $revision->getFillable() )->toContain( 'data' )
		->and( $revision->getFillable() )->toContain( 'name' );
} );

test( 'content revision casts data as array', function (): void {
	$revision = ContentRevision::create( [
		'content_id' => $this->content->id,
		'user_id'    => $this->user->id,
		'type'       => 'manual',
		'data'       => [ 'blocks' => [] ],
		'created_at' => now(),
	] );

	$revision->refresh();

	expect( $revision->data )->toBeArray();
} );

test( 'content revision has content relationship', function (): void {
	$revision = new ContentRevision();

	expect( $revision->content() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'content revision has user relationship', function (): void {
	$revision = new ContentRevision();

	expect( $revision->user() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'content revision autosaves scope filters correctly', function (): void {
	ContentRevision::create( [
		'content_id' => $this->content->id,
		'user_id'    => $this->user->id,
		'type'       => 'autosave',
		'data'       => [],
		'created_at' => now(),
	] );

	ContentRevision::create( [
		'content_id' => $this->content->id,
		'user_id'    => $this->user->id,
		'type'       => 'manual',
		'data'       => [],
		'created_at' => now(),
	] );

	expect( ContentRevision::autosaves()->count() )->toBe( 1 )
		->and( ContentRevision::manual()->count() )->toBe( 1 );
} );

test( 'content revision of type scope filters correctly', function (): void {
	ContentRevision::create( [
		'content_id' => $this->content->id,
		'user_id'    => $this->user->id,
		'type'       => 'named',
		'name'       => 'Before redesign',
		'data'       => [],
		'created_at' => now(),
	] );

	expect( ContentRevision::ofType( 'named' )->count() )->toBe( 1 )
		->and( ContentRevision::named()->count() )->toBe( 1 );
} );
