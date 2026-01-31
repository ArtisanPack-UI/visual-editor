<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\ContentRevision;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user    = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'API Test Page',
		'slug'      => 'api-test-page',
		'blocks'    => [ [ 'id' => 've-1', 'type' => 'heading', 'data' => [], 'settings' => [] ] ],
		'settings'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
} );

test( 'save endpoint updates content and returns json', function (): void {
	$this->actingAs( $this->user );

	$response = $this->postJson(
		route( 'visual-editor.api.save', $this->content ),
		[
			'title'  => 'Updated via API',
			'blocks' => [ [ 'id' => 've-2', 'type' => 'text', 'data' => [], 'settings' => [] ] ],
		],
	);

	$response->assertSuccessful()
		->assertJsonPath( 'message', 'Content saved.' );

	$this->content->refresh();

	expect( $this->content->title )->toBe( 'Updated via API' );
} );

test( 'autosave endpoint creates revision', function (): void {
	$this->actingAs( $this->user );

	$response = $this->postJson(
		route( 'visual-editor.api.autosave', $this->content ),
		[
			'blocks' => [ [ 'id' => 've-3', 'type' => 'text', 'data' => [], 'settings' => [] ] ],
		],
	);

	$response->assertSuccessful()
		->assertJsonStructure( [ 'message', 'revision_id' ] );

	$revision = ContentRevision::find( $response->json( 'revision_id' ) );

	expect( $revision )->not->toBeNull()
		->and( $revision->type )->toBe( 'autosave' );
} );

test( 'publish endpoint publishes content', function (): void {
	$this->actingAs( $this->user );

	$response = $this->postJson(
		route( 'visual-editor.api.publish', $this->content ),
	);

	$response->assertSuccessful()
		->assertJsonPath( 'message', 'Content published.' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'published' );
} );

test( 'unpublish endpoint unpublishes content', function (): void {
	$this->content->update( [
		'status'       => 'published',
		'published_at' => now(),
	] );

	$this->actingAs( $this->user );

	$response = $this->postJson(
		route( 'visual-editor.api.unpublish', $this->content ),
	);

	$response->assertSuccessful()
		->assertJsonPath( 'message', 'Content unpublished.' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'draft' );
} );

test( 'schedule endpoint schedules content with datetime', function (): void {
	$this->actingAs( $this->user );

	$response = $this->postJson(
		route( 'visual-editor.api.schedule', $this->content ),
		[
			'scheduled_at' => '2027-06-15 10:00:00',
		],
	);

	$response->assertSuccessful()
		->assertJsonPath( 'message', 'Content scheduled.' );

	$this->content->refresh();

	expect( $this->content->status )->toBe( 'scheduled' );
} );

test( 'unauthenticated requests return 401', function (): void {
	$this->postJson(
		route( 'visual-editor.api.save', $this->content ),
		[ 'title' => 'Should fail' ],
	)->assertUnauthorized();
} );

// =========================================
// Authorization (non-author)
// =========================================

test( 'save endpoint returns 403 for non-author', function (): void {
	$otherUser = User::factory()->create();

	$this->actingAs( $otherUser );

	$this->postJson(
		route( 'visual-editor.api.save', $this->content ),
		[ 'title' => 'Unauthorized update' ],
	)->assertForbidden();
} );

test( 'autosave endpoint returns 403 for non-author', function (): void {
	$otherUser = User::factory()->create();

	$this->actingAs( $otherUser );

	$this->postJson(
		route( 'visual-editor.api.autosave', $this->content ),
		[ 'blocks' => [] ],
	)->assertForbidden();
} );

test( 'publish endpoint returns 403 for non-author', function (): void {
	$otherUser = User::factory()->create();

	$this->actingAs( $otherUser );

	$this->postJson(
		route( 'visual-editor.api.publish', $this->content ),
	)->assertForbidden();
} );

test( 'unpublish endpoint returns 403 for non-author', function (): void {
	$this->content->update( [
		'status'       => 'published',
		'published_at' => now(),
	] );

	$otherUser = User::factory()->create();

	$this->actingAs( $otherUser );

	$this->postJson(
		route( 'visual-editor.api.unpublish', $this->content ),
	)->assertForbidden();
} );

test( 'schedule endpoint returns 403 for non-author', function (): void {
	$otherUser = User::factory()->create();

	$this->actingAs( $otherUser );

	$this->postJson(
		route( 'visual-editor.api.schedule', $this->content ),
		[ 'scheduled_at' => '2027-06-15 10:00:00' ],
	)->assertForbidden();
} );
