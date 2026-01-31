<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\UserSection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user = User::factory()->create();
} );

test( 'user section has correct table name', function (): void {
	$section = new UserSection();

	expect( $section->getTable() )->toBe( 've_user_sections' );
} );

test( 'user section has correct fillable attributes', function (): void {
	$section = new UserSection();

	expect( $section->getFillable() )->toContain( 'user_id' )
		->and( $section->getFillable() )->toContain( 'name' )
		->and( $section->getFillable() )->toContain( 'blocks' )
		->and( $section->getFillable() )->toContain( 'is_shared' );
} );

test( 'user section casts json fields correctly', function (): void {
	$section = UserSection::create( [
		'user_id' => $this->user->id,
		'name'    => 'My Section',
		'blocks'  => [ [ 'type' => 'heading' ] ],
		'styles'  => [ 'padding' => '2rem' ],
	] );

	$section->refresh();

	expect( $section->blocks )->toBeArray()
		->and( $section->styles )->toBeArray();
} );

test( 'user section has user relationship', function (): void {
	$section = new UserSection();

	expect( $section->user() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'user section shared scope filters correctly', function (): void {
	UserSection::create( [
		'user_id'   => $this->user->id,
		'name'      => 'Shared Section',
		'blocks'    => [],
		'is_shared' => true,
	] );

	UserSection::create( [
		'user_id'   => $this->user->id,
		'name'      => 'Private Section',
		'blocks'    => [],
		'is_shared' => false,
	] );

	expect( UserSection::shared()->count() )->toBe( 1 );
} );

test( 'user section in category scope filters correctly', function (): void {
	UserSection::create( [
		'user_id'  => $this->user->id,
		'name'     => 'Hero Section',
		'category' => 'hero',
		'blocks'   => [],
	] );

	UserSection::create( [
		'user_id'  => $this->user->id,
		'name'     => 'CTA Section',
		'category' => 'cta',
		'blocks'   => [],
	] );

	expect( UserSection::inCategory( 'hero' )->count() )->toBe( 1 );
} );
