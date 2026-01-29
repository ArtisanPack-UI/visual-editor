<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user = User::factory()->create();
} );

test( 'content has correct table name', function (): void {
	$content = new Content();

	expect( $content->getTable() )->toBe( 've_contents' );
} );

test( 'content has correct fillable attributes', function (): void {
	$content = new Content();

	expect( $content->getFillable() )->toContain( 'uuid' )
		->and( $content->getFillable() )->toContain( 'title' )
		->and( $content->getFillable() )->toContain( 'slug' )
		->and( $content->getFillable() )->toContain( 'blocks' )
		->and( $content->getFillable() )->toContain( 'status' )
		->and( $content->getFillable() )->toContain( 'author_id' );
} );

test( 'content casts json fields correctly', function (): void {
	$content = Content::create( [
		'title'     => 'Test Content',
		'slug'      => 'test-content',
		'blocks'    => [ [ 'type' => 'hero' ] ],
		'settings'  => [ 'key' => 'value' ],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	$content->refresh();

	expect( $content->blocks )->toBeArray()
		->and( $content->settings )->toBeArray();
} );

test( 'content generates uuid on creation', function (): void {
	$content = Content::create( [
		'title'     => 'UUID Test',
		'slug'      => 'uuid-test',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( $content->uuid )->not->toBeNull()
		->and( $content->uuid )->toBeString();
} );

test( 'content generates slug from title on creation', function (): void {
	$content = Content::create( [
		'title'     => 'My Test Page',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( $content->slug )->toBe( 'my-test-page' );
} );

test( 'content generates unique slug when duplicate exists', function (): void {
	Content::create( [
		'title'     => 'Duplicate Title',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	$second = Content::create( [
		'title'     => 'Duplicate Title',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( $second->slug )->toBe( 'duplicate-title-1' );
} );

test( 'content allows same slug across different content types', function (): void {
	Content::create( [
		'title'        => 'Same Title',
		'content_type' => 'page',
		'blocks'       => [],
		'status'       => 'draft',
		'author_id'    => $this->user->id,
	] );

	$post = Content::create( [
		'title'        => 'Same Title',
		'content_type' => 'post',
		'blocks'       => [],
		'status'       => 'draft',
		'author_id'    => $this->user->id,
	] );

	expect( $post->slug )->toBe( 'same-title' );
} );

test( 'content uses uuid as route key', function (): void {
	$content = new Content();

	expect( $content->getRouteKeyName() )->toBe( 'uuid' );
} );

test( 'content has author relationship', function (): void {
	$content = new Content();

	expect( $content->author() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'content has revisions relationship', function (): void {
	$content = new Content();

	expect( $content->revisions() )->toBeInstanceOf( HasMany::class );
} );

test( 'content has experiments relationship', function (): void {
	$content = new Content();

	expect( $content->experiments() )->toBeInstanceOf( HasMany::class );
} );

test( 'content has editor lock relationship', function (): void {
	$content = new Content();

	expect( $content->editorLock() )->toBeInstanceOf( HasOne::class );
} );

test( 'content published scope filters correctly', function (): void {
	Content::create( [
		'title'     => 'Published',
		'slug'      => 'published',
		'blocks'    => [],
		'status'    => 'published',
		'author_id' => $this->user->id,
	] );

	Content::create( [
		'title'     => 'Draft',
		'slug'      => 'draft',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( Content::published()->count() )->toBe( 1 );
} );

test( 'content draft scope filters correctly', function (): void {
	Content::create( [
		'title'     => 'Published',
		'slug'      => 'published-2',
		'blocks'    => [],
		'status'    => 'published',
		'author_id' => $this->user->id,
	] );

	Content::create( [
		'title'     => 'Draft',
		'slug'      => 'draft-2',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( Content::draft()->count() )->toBe( 1 );
} );

test( 'content of type scope filters correctly', function (): void {
	Content::create( [
		'title'        => 'Page',
		'slug'         => 'page-1',
		'blocks'       => [],
		'content_type' => 'page',
		'status'       => 'draft',
		'author_id'    => $this->user->id,
	] );

	Content::create( [
		'title'        => 'Post',
		'slug'         => 'post-1',
		'blocks'       => [],
		'content_type' => 'post',
		'status'       => 'draft',
		'author_id'    => $this->user->id,
	] );

	expect( Content::ofType( 'page' )->count() )->toBe( 1 );
} );

test( 'content generates slug from empty title', function (): void {
	$content = Content::create( [
		'title'     => '',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( $content->slug )->not->toBeEmpty()
		->and( $content->slug )->toStartWith( 'content-' );
} );

test( 'content generates slug when title is null', function (): void {
	$content = Content::create( [
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	expect( $content->slug )->not->toBeEmpty()
		->and( $content->slug )->toStartWith( 'content-' );
} );

test( 'content supports soft deletes', function (): void {
	$content = Content::create( [
		'title'     => 'Soft Delete Test',
		'slug'      => 'soft-delete',
		'blocks'    => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );

	$content->delete();

	expect( Content::count() )->toBe( 0 )
		->and( Content::withTrashed()->count() )->toBe( 1 );
} );
