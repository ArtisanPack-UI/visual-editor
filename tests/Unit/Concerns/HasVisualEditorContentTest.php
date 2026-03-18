<?php

/**
 * HasVisualEditorContent Trait Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Concerns
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Unit\Concerns\Stubs\TestPost;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );

	Schema::create( 'test_posts', function ( $table ): void {
		$table->id();
		$table->json( 'blocks' )->nullable();
		$table->string( 'status' )->nullable();
		$table->timestamp( 'scheduled_at' )->nullable();
		$table->timestamps();
	} );
} );

it( 'automatically casts blocks to array', function (): void {
	$post = TestPost::create( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
	] );

	$post->refresh();

	expect( $post->blocks )->toBeArray()
		->and( $post->blocks[0]['type'] )->toBe( 'paragraph' );
} );

it( 'returns empty array when blocks is null via getBlocks', function (): void {
	$post = TestPost::create();

	expect( $post->getBlocks() )->toBeArray()->toBeEmpty();
} );

it( 'gets blocks via getBlocks', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'level' => 2, 'content' => 'Title' ] ],
	];

	$post = TestPost::create( [ 'blocks' => $blocks ] );

	expect( $post->getBlocks() )->toEqual( $blocks );
} );

it( 'sets blocks via setBlocks', function (): void {
	$post   = TestPost::create();
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ],
	];

	$post->setBlocks( $blocks );

	expect( $post->blocks )->toEqual( $blocks );
} );

it( 'saves blocks and status from editor metadata', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'status' => 'published',
	] );

	$post->refresh();

	expect( $post->blocks )->toHaveCount( 1 )
		->and( $post->status )->toBe( 'published' );
} );

it( 'saves scheduled date from editor metadata', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks'        => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'status'        => 'scheduled',
		'scheduledDate' => '2026-12-31 00:00:00',
	] );

	$post->refresh();

	expect( $post->status )->toBe( 'scheduled' )
		->and( $post->scheduled_at )->not->toBeNull();
} );

it( 'creates a revision after saveFromEditor', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Rev1' ] ] ],
	] );

	expect( $post->revisions()->count() )->toBe( 1 );

	$revision = $post->revisions()->first();

	expect( $revision->blocks )->toEqual( $post->getBlocks() )
		->and( $revision->document_type )->toBe( TestPost::class )
		->and( $revision->document_id )->toBe( $post->id );
} );

it( 'stores the authenticated user id on revision', function (): void {
	DB::table( 'users' )->insert( [
		'id'    => 42,
		'name'  => 'Test User',
		'email' => 'test@example.com',
	] );

	Auth::loginUsingId( 42 );

	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Test' ] ] ],
	] );

	$revision = $post->revisions()->first();

	expect( $revision->user_id )->toBe( 42 );
} );

it( 'returns the latest revision', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'First' ] ] ],
	] );

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Second' ] ] ],
	] );

	$latest = $post->latestRevision();

	expect( $latest )->toBeInstanceOf( Revision::class )
		->and( $latest->blocks[0]['attributes']['content'] )->toBe( 'Second' );
} );

it( 'restores blocks from a revision', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Original' ] ] ],
	] );

	$firstRevisionId = $post->revisions()->first()->id;

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Updated' ] ] ],
	] );

	$post->restoreRevision( $firstRevisionId );
	$post->refresh();

	expect( $post->blocks[0]['attributes']['content'] )->toBe( 'Original' );
} );

it( 'creates a new revision after restoring', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Original' ] ] ],
	] );

	$firstRevisionId = $post->revisions()->first()->id;

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Updated' ] ] ],
	] );

	$post->restoreRevision( $firstRevisionId );

	expect( $post->revisions()->count() )->toBe( 3 );
} );

it( 'prunes revisions beyond the configured maximum', function (): void {
	config()->set( 'artisanpack.visual-editor.persistence.max_revisions', 3 );

	$post = TestPost::create();

	for ( $i = 1; $i <= 5; $i++ ) {
		$post->saveFromEditor( [
			'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => "Rev {$i}" ] ] ],
		] );
	}

	expect( $post->revisions()->count() )->toBe( 3 );

	$latest = $post->latestRevision();

	expect( $latest->blocks[0]['attributes']['content'] )->toBe( 'Rev 5' );
} );

it( 'returns morphMany relationship for revisions', function (): void {
	$post = TestPost::create();

	expect( $post->revisions() )->toBeInstanceOf( Illuminate\Database\Eloquent\Relations\MorphMany::class );
} );

it( 'does not set status when meta does not include it', function (): void {
	$post = TestPost::create( [ 'status' => 'draft' ] );

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
	] );

	$post->refresh();

	expect( $post->status )->toBe( 'draft' );
} );

it( 'does not set scheduled_at when meta does not include scheduledDate', function (): void {
	$post = TestPost::create();

	$post->saveFromEditor( [
		'blocks' => [ [ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ] ],
		'status' => 'published',
	] );

	$post->refresh();

	expect( $post->scheduled_at )->toBeNull();
} );
