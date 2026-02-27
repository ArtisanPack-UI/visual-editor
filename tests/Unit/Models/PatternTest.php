<?php

/**
 * Pattern Model Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

it( 'casts blocks to array', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Hello' ] ],
	];

	$pattern = Pattern::create( [
		'name'   => 'Test Pattern',
		'slug'   => 'test-pattern',
		'blocks' => $blocks,
	] );

	$pattern->refresh();

	expect( $pattern->blocks )->toBeArray()
		->and( $pattern->blocks )->toEqual( $blocks );
} );

it( 'filters by category scope', function (): void {
	Pattern::create( [
		'name'     => 'Header Pattern',
		'slug'     => 'header-pattern',
		'blocks'   => [],
		'category' => 'header',
	] );

	Pattern::create( [
		'name'     => 'Footer Pattern',
		'slug'     => 'footer-pattern',
		'blocks'   => [],
		'category' => 'footer',
	] );

	$headers = Pattern::byCategory( 'header' )->get();

	expect( $headers )->toHaveCount( 1 )
		->and( $headers->first()->name )->toBe( 'Header Pattern' );
} );

it( 'filters by user scope', function (): void {
	DB::table( 'users' )->insert( [
		[ 'id' => 1, 'name' => 'User 1', 'email' => 'user1@test.com' ],
		[ 'id' => 2, 'name' => 'User 2', 'email' => 'user2@test.com' ],
	] );

	Pattern::create( [
		'name'    => 'User Pattern',
		'slug'    => 'user-pattern',
		'blocks'  => [],
		'user_id' => 1,
	] );

	Pattern::create( [
		'name'    => 'Other Pattern',
		'slug'    => 'other-pattern',
		'blocks'  => [],
		'user_id' => 2,
	] );

	$patterns = Pattern::byUser( 1 )->get();

	expect( $patterns )->toHaveCount( 1 )
		->and( $patterns->first()->name )->toBe( 'User Pattern' );
} );

it( 'has fillable attributes', function (): void {
	$pattern = new Pattern();

	expect( $pattern->getFillable() )->toContain( 'name', 'slug', 'blocks', 'category', 'user_id' );
} );

it( 'enforces unique slug', function (): void {
	Pattern::create( [
		'name'   => 'First',
		'slug'   => 'unique-slug',
		'blocks' => [],
	] );

	expect( fn () => Pattern::create( [
		'name'   => 'Second',
		'slug'   => 'unique-slug',
		'blocks' => [],
	] ) )->toThrow( Illuminate\Database\QueryException::class );
} );
