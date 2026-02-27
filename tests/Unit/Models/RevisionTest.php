<?php

/**
 * Revision Model Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

it( 'casts blocks to array', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'level' => 2, 'content' => 'Title' ] ],
	];

	$revision = Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => $blocks,
		'created_at'    => now(),
	] );

	$revision->refresh();

	expect( $revision->blocks )->toBeArray()
		->and( $revision->blocks )->toEqual( $blocks );
} );

it( 'filters by document scope', function (): void {
	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 2,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	Revision::create( [
		'document_type' => 'page',
		'document_id'   => 1,
		'blocks'        => [],
		'created_at'    => now(),
	] );

	$revisions = Revision::forDocument( 'post', 1 )->get();

	expect( $revisions )->toHaveCount( 1 );
} );

it( 'filters by user scope', function (): void {
	DB::table( 'users' )->insert( [
		[ 'id' => 5, 'name' => 'User 5', 'email' => 'user5@test.com' ],
		[ 'id' => 10, 'name' => 'User 10', 'email' => 'user10@test.com' ],
	] );

	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'user_id'       => 5,
		'created_at'    => now(),
	] );

	Revision::create( [
		'document_type' => 'post',
		'document_id'   => 1,
		'blocks'        => [],
		'user_id'       => 10,
		'created_at'    => now(),
	] );

	$revisions = Revision::byUser( 5 )->get();

	expect( $revisions )->toHaveCount( 1 );
} );

it( 'does not use updated_at timestamp', function (): void {
	$revision = new Revision();

	expect( $revision->timestamps )->toBeFalse();
} );

it( 'has fillable attributes', function (): void {
	$revision = new Revision();

	expect( $revision->getFillable() )->toContain( 'document_type', 'document_id', 'blocks', 'user_id', 'created_at' );
} );
