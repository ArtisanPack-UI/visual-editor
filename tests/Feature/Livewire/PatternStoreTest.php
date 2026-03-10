<?php

/**
 * Pattern Store Livewire Component Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

function createTestUser(): Authenticatable
{
	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Test User',
		'email'      => 'test@example.com',
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user     = new class extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Test User';
	$user->email = 'test@example.com';

	return $user;
}

it( 'saves a pattern and creates database record', function (): void {
	$user = createTestUser();

	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Title' ] ],
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Body text' ] ],
	];

	Livewire::actingAs( $user )
		->test( 'visual-editor::pattern-store' )
		->set( 'name', 'Hero Section' )
		->set( 'category', 'header' )
		->call( 'savePattern', $blocks )
		->assertDispatched( 've-pattern-saved' );

	expect( Pattern::where( 'slug', 'hero-section' )->exists() )->toBeTrue();
} );

it( 'does not save pattern for unauthenticated user', function (): void {
	$blocks = [
		[ 'type' => 'heading', 'attributes' => [ 'content' => 'Title' ] ],
	];

	Livewire::test( 'visual-editor::pattern-store' )
		->set( 'name', 'Should Not Save' )
		->call( 'savePattern', $blocks )
		->assertNotDispatched( 've-pattern-saved' );

	expect( Pattern::count() )->toBe( 0 );
} );

it( 'loads a pattern and dispatches event', function (): void {
	$blocks = [
		[ 'type' => 'paragraph', 'attributes' => [ 'content' => 'Loaded' ] ],
	];

	$pattern = Pattern::create( [
		'name'   => 'Test Pattern',
		'slug'   => 'test-pattern',
		'blocks' => $blocks,
	] );

	Livewire::test( 'visual-editor::pattern-store' )
		->call( 'loadPattern', $pattern->id )
		->assertDispatched( 've-pattern-loaded' );
} );

it( 'deletes a pattern and removes record', function (): void {
	$pattern = Pattern::create( [
		'name'   => 'Delete Me',
		'slug'   => 'delete-me',
		'blocks' => [],
	] );

	Livewire::test( 'visual-editor::pattern-store' )
		->call( 'deletePattern', $pattern->id )
		->assertDispatched( 've-pattern-deleted' );

	expect( Pattern::find( $pattern->id ) )->toBeNull();
} );

it( 'validates pattern name is required', function (): void {
	$user = createTestUser();

	Livewire::actingAs( $user )
		->test( 'visual-editor::pattern-store' )
		->set( 'name', '' )
		->call( 'savePattern', [ [ 'type' => 'paragraph' ] ] )
		->assertHasErrors( [ 'name' ] );
} );

it( 'returns computed patterns collection', function (): void {
	Pattern::create( [
		'name'   => 'Pattern A',
		'slug'   => 'pattern-a',
		'blocks' => [],
	] );

	Pattern::create( [
		'name'   => 'Pattern B',
		'slug'   => 'pattern-b',
		'blocks' => [],
	] );

	Livewire::test( 'visual-editor::pattern-store' );

	expect( Pattern::count() )->toBe( 2 );
} );
