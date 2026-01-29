<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\EditorLock;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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

test( 'editor lock has correct table name', function (): void {
	$lock = new EditorLock();

	expect( $lock->getTable() )->toBe( 've_editor_locks' );
} );

test( 'editor lock has correct fillable attributes', function (): void {
	$lock = new EditorLock();

	expect( $lock->getFillable() )->toContain( 'content_id' )
		->and( $lock->getFillable() )->toContain( 'user_id' )
		->and( $lock->getFillable() )->toContain( 'session_id' )
		->and( $lock->getFillable() )->toContain( 'started_at' )
		->and( $lock->getFillable() )->toContain( 'last_heartbeat' );
} );

test( 'editor lock has content relationship', function (): void {
	$lock = new EditorLock();

	expect( $lock->content() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'editor lock has user relationship', function (): void {
	$lock = new EditorLock();

	expect( $lock->user() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'editor lock heartbeat updates timestamp', function (): void {
	$lock = EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $this->user->id,
		'session_id'     => 'test-session-123',
		'started_at'     => Carbon::now()->subMinutes( 5 ),
		'last_heartbeat' => Carbon::now()->subMinutes( 1 ),
	] );

	$oldHeartbeat = $lock->last_heartbeat;
	$lock->heartbeat();
	$lock->refresh();

	expect( $lock->last_heartbeat->gt( $oldHeartbeat ) )->toBeTrue();
} );

test( 'editor lock detects expired locks', function (): void {
	$lock = EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $this->user->id,
		'session_id'     => 'expired-session',
		'started_at'     => Carbon::now()->subMinutes( 10 ),
		'last_heartbeat' => Carbon::now()->subSeconds( 200 ),
	] );

	expect( $lock->isExpired() )->toBeTrue();
} );

test( 'editor lock detects active locks', function (): void {
	$lock = EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $this->user->id,
		'session_id'     => 'active-session',
		'started_at'     => Carbon::now()->subMinutes( 1 ),
		'last_heartbeat' => Carbon::now()->subSeconds( 10 ),
	] );

	expect( $lock->isExpired() )->toBeFalse();
} );

test( 'editor lock expired scope filters correctly', function (): void {
	EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $this->user->id,
		'session_id'     => 'expired-session',
		'started_at'     => Carbon::now()->subMinutes( 10 ),
		'last_heartbeat' => Carbon::now()->subSeconds( 200 ),
	] );

	$user2 = User::factory()->create();

	EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $user2->id,
		'session_id'     => 'active-session',
		'started_at'     => Carbon::now()->subMinutes( 1 ),
		'last_heartbeat' => Carbon::now()->subSeconds( 10 ),
	] );

	expect( EditorLock::expired()->count() )->toBe( 1 )
		->and( EditorLock::active()->count() )->toBe( 1 );
} );

test( 'editor lock casts datetime fields correctly', function (): void {
	$lock = EditorLock::create( [
		'content_id'     => $this->content->id,
		'user_id'        => $this->user->id,
		'session_id'     => 'cast-test',
		'started_at'     => Carbon::now(),
		'last_heartbeat' => Carbon::now(),
	] );

	$lock->refresh();

	expect( $lock->started_at )->toBeInstanceOf( Carbon::class )
		->and( $lock->last_heartbeat )->toBeInstanceOf( Carbon::class );
} );
