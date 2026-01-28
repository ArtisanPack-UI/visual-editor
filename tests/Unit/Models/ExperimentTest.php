<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\Experiment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user    = User::factory()->create();
	$this->content = Content::create( [
		'title'     => 'Test Content',
		'slug'      => 'test-content',
		'sections'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
} );

test( 'experiment has correct table name', function (): void {
	$experiment = new Experiment();

	expect( $experiment->getTable() )->toBe( 've_experiments' );
} );

test( 'experiment has correct fillable attributes', function (): void {
	$experiment = new Experiment();

	expect( $experiment->getFillable() )->toContain( 'content_id' )
		->and( $experiment->getFillable() )->toContain( 'created_by' )
		->and( $experiment->getFillable() )->toContain( 'name' )
		->and( $experiment->getFillable() )->toContain( 'goal_type' )
		->and( $experiment->getFillable() )->toContain( 'status' );
} );

test( 'experiment has content relationship', function (): void {
	$experiment = new Experiment();

	expect( $experiment->content() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'experiment has creator relationship', function (): void {
	$experiment = new Experiment();

	expect( $experiment->creator() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'experiment has variants relationship', function (): void {
	$experiment = new Experiment();

	expect( $experiment->variants() )->toBeInstanceOf( HasMany::class );
} );

test( 'experiment has winner variant relationship', function (): void {
	$experiment = new Experiment();

	expect( $experiment->winnerVariant() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'experiment running scope filters correctly', function (): void {
	Experiment::create( [
		'content_id' => $this->content->id,
		'created_by' => $this->user->id,
		'name'       => 'Running Test',
		'goal_type'  => 'clicks',
		'status'     => 'running',
	] );

	Experiment::create( [
		'content_id' => $this->content->id,
		'created_by' => $this->user->id,
		'name'       => 'Draft Test',
		'goal_type'  => 'clicks',
		'status'     => 'draft',
	] );

	expect( Experiment::running()->count() )->toBe( 1 )
		->and( Experiment::draft()->count() )->toBe( 1 );
} );

test( 'experiment ended scope filters correctly', function (): void {
	Experiment::create( [
		'content_id' => $this->content->id,
		'created_by' => $this->user->id,
		'name'       => 'Ended Test',
		'goal_type'  => 'conversions',
		'status'     => 'ended',
	] );

	expect( Experiment::ended()->count() )->toBe( 1 );
} );

test( 'experiment casts datetime fields correctly', function (): void {
	$experiment = Experiment::create( [
		'content_id' => $this->content->id,
		'created_by' => $this->user->id,
		'name'       => 'Date Test',
		'goal_type'  => 'clicks',
		'status'     => 'running',
		'started_at' => now(),
	] );

	$experiment->refresh();

	expect( $experiment->started_at )->toBeInstanceOf( Illuminate\Support\Carbon::class );
} );
