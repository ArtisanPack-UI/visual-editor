<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\ContentRevision;
use ArtisanPackUI\VisualEditor\Services\ContentService;
use Illuminate\Support\Carbon;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user    = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'Test Page',
		'slug'      => 'test-page',
		'blocks'    => [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ],
		'settings'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
	$this->service = app( ContentService::class );
} );

// =========================================
// saveDraft
// =========================================

test( 'saveDraft updates content and creates manual revision', function (): void {
	$result = $this->service->saveDraft( $this->content, [
		'title'  => 'Updated Title',
		'blocks' => [ [ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ] ],
	], $this->user->id );

	expect( $result->title )->toBe( 'Updated Title' )
		->and( $result->blocks )->toHaveCount( 1 )
		->and( $result->blocks[0]['id'] )->toBe( 've-2' );

	$revision = ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'manual' )
		->first();

	expect( $revision )->not->toBeNull()
		->and( $revision->user_id )->toBe( $this->user->id )
		->and( $revision->data['title'] )->toBe( 'Updated Title' );
} );

test( 'saveDraft updates excerpt, template, and meta fields', function (): void {
	$this->service->saveDraft( $this->content, [
		'excerpt'          => 'A test excerpt.',
		'template'         => 'full-width',
		'meta_title'       => 'SEO Title',
		'meta_description' => 'SEO Description',
		'og_image'         => '/images/og.jpg',
	], $this->user->id );

	$this->content->refresh();

	expect( $this->content->excerpt )->toBe( 'A test excerpt.' )
		->and( $this->content->template )->toBe( 'full-width' )
		->and( $this->content->meta_title )->toBe( 'SEO Title' )
		->and( $this->content->meta_description )->toBe( 'SEO Description' )
		->and( $this->content->og_image )->toBe( '/images/og.jpg' );
} );

test( 'saveDraft updates slug when provided', function (): void {
	$this->service->saveDraft( $this->content, [
		'slug' => 'updated-slug',
	], $this->user->id );

	$this->content->refresh();

	expect( $this->content->slug )->toBe( 'updated-slug' );
} );

test( 'saveDraft only updates provided fields', function (): void {
	$originalTitle = $this->content->title;

	$this->service->saveDraft( $this->content, [
		'excerpt' => 'New excerpt',
	], $this->user->id );

	$this->content->refresh();

	expect( $this->content->title )->toBe( $originalTitle )
		->and( $this->content->excerpt )->toBe( 'New excerpt' );
} );

// =========================================
// autosave
// =========================================

test( 'autosave creates autosave revision without modifying content', function (): void {
	$originalTitle = $this->content->title;

	$revision = $this->service->autosave( $this->content, [
		'blocks' => [ [ 'id' => 've-99', 'type' => 'paragraph', 'data' => [], 'settings' => [] ] ],
	], $this->user->id );

	$this->content->refresh();

	expect( $revision->type )->toBe( 'autosave' )
		->and( $revision->content_id )->toBe( $this->content->id )
		->and( $revision->data['blocks'][0]['id'] )->toBe( 've-99' )
		->and( $this->content->title )->toBe( $originalTitle );
} );

test( 'autosave prunes excess autosaves', function (): void {
	config( [ 'artisanpack.visual-editor.revisions.max_autosaves_per_content' => 3 ] );

	for ( $i = 0; $i < 5; $i++ ) {
		$this->service->autosave( $this->content, [
			'blocks' => [ [ 'id' => "ve-{$i}", 'type' => 'text', 'data' => [], 'settings' => [] ] ],
		], $this->user->id );
	}

	$autosaveCount = ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'autosave' )
		->count();

	expect( $autosaveCount )->toBe( 3 );
} );

// =========================================
// publish
// =========================================

test( 'publish sets status to published and published_at', function (): void {
	Carbon::setTestNow( '2026-01-28 12:00:00' );

	$result = $this->service->publish( $this->content, $this->user->id );

	expect( $result->status )->toBe( 'published' )
		->and( $result->published_at )->not->toBeNull()
		->and( $result->scheduled_at )->toBeNull();

	Carbon::setTestNow();
} );

test( 'publish creates a publish-type revision', function (): void {
	$this->service->publish( $this->content, $this->user->id );

	$revision = ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'publish' )
		->first();

	expect( $revision )->not->toBeNull()
		->and( $revision->data['status'] )->toBe( 'published' );
} );

// =========================================
// unpublish
// =========================================

test( 'unpublish reverts status to draft and nulls published_at', function (): void {
	$this->content->update( [
		'status'       => 'published',
		'published_at' => now(),
	] );

	$result = $this->service->unpublish( $this->content, $this->user->id );

	expect( $result->status )->toBe( 'draft' )
		->and( $result->published_at )->toBeNull();
} );

// =========================================
// schedule
// =========================================

test( 'schedule sets status to scheduled and scheduled_at', function (): void {
	$publishAt = Carbon::parse( '2026-06-15 10:00:00' );

	$result = $this->service->schedule( $this->content, $publishAt, $this->user->id );

	expect( $result->status )->toBe( 'scheduled' )
		->and( $result->scheduled_at->toDateTimeString() )->toBe( '2026-06-15 10:00:00' );
} );

// =========================================
// submitForReview
// =========================================

test( 'submitForReview sets status to pending', function (): void {
	$result = $this->service->submitForReview( $this->content, $this->user->id );

	expect( $result->status )->toBe( 'pending' );

	$revision = ContentRevision::where( 'content_id', $this->content->id )
		->where( 'type', 'manual' )
		->first();

	expect( $revision )->not->toBeNull();
} );

// =========================================
// runPrePublishChecks
// =========================================

test( 'runPrePublishChecks returns pass for content with title', function (): void {
	$checks = $this->service->runPrePublishChecks( $this->content );

	$titleCheck = collect( $checks )->firstWhere( 'key', 'title' );

	expect( $titleCheck['status'] )->toBe( 'pass' );
} );

test( 'runPrePublishChecks returns fail for content without title', function (): void {
	$this->content->title = '';
	$this->content->save();

	$checks = $this->service->runPrePublishChecks( $this->content );

	$titleCheck = collect( $checks )->firstWhere( 'key', 'title' );

	expect( $titleCheck['status'] )->toBe( 'fail' );
} );

test( 'runPrePublishChecks returns warning for missing featured image when not required', function (): void {
	config( [ 'artisanpack.visual-editor.content.require_featured_image' => false ] );

	$checks = $this->service->runPrePublishChecks( $this->content );

	$imageCheck = collect( $checks )->firstWhere( 'key', 'featured_image' );

	expect( $imageCheck['status'] )->toBe( 'warning' );
} );

test( 'runPrePublishChecks returns fail for missing featured image when required', function (): void {
	config( [ 'artisanpack.visual-editor.content.require_featured_image' => true ] );

	$checks = $this->service->runPrePublishChecks( $this->content );

	$imageCheck = collect( $checks )->firstWhere( 'key', 'featured_image' );

	expect( $imageCheck['status'] )->toBe( 'fail' );
} );

test( 'runPrePublishChecks returns warning for missing meta title', function (): void {
	$checks = $this->service->runPrePublishChecks( $this->content );

	$metaTitleCheck = collect( $checks )->firstWhere( 'key', 'meta_title' );

	expect( $metaTitleCheck['status'] )->toBe( 'warning' );
} );

test( 'runPrePublishChecks returns pass for content with meta description', function (): void {
	$this->content->meta_description = 'A great page.';
	$this->content->save();

	$checks = $this->service->runPrePublishChecks( $this->content );

	$metaDescCheck = collect( $checks )->firstWhere( 'key', 'meta_description' );

	expect( $metaDescCheck['status'] )->toBe( 'pass' );
} );
