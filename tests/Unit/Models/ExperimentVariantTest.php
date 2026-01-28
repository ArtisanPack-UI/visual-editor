<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Content;
use ArtisanPackUI\VisualEditor\Models\Experiment;
use ArtisanPackUI\VisualEditor\Models\ExperimentVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Models\User;

beforeEach( function (): void {
	$this->user       = User::factory()->create();
	$this->content    = Content::create( [
		'title'     => 'Test Content',
		'slug'      => 'test-content',
		'sections'  => [],
		'status'    => 'draft',
		'author_id' => $this->user->id,
	] );
	$this->experiment = Experiment::create( [
		'content_id' => $this->content->id,
		'created_by' => $this->user->id,
		'name'       => 'Test Experiment',
		'goal_type'  => 'clicks',
		'status'     => 'draft',
	] );
} );

test( 'experiment variant has correct table name', function (): void {
	$variant = new ExperimentVariant();

	expect( $variant->getTable() )->toBe( 've_experiment_variants' );
} );

test( 'experiment variant has correct fillable attributes', function (): void {
	$variant = new ExperimentVariant();

	expect( $variant->getFillable() )->toContain( 'experiment_id' )
		->and( $variant->getFillable() )->toContain( 'name' )
		->and( $variant->getFillable() )->toContain( 'is_control' )
		->and( $variant->getFillable() )->toContain( 'content_data' )
		->and( $variant->getFillable() )->toContain( 'impressions' )
		->and( $variant->getFillable() )->toContain( 'conversions' );
} );

test( 'experiment variant casts json fields correctly', function (): void {
	$variant = ExperimentVariant::create( [
		'experiment_id' => $this->experiment->id,
		'name'          => 'Variant A',
		'content_data'  => [ 'headline' => 'Test' ],
	] );

	$variant->refresh();

	expect( $variant->content_data )->toBeArray();
} );

test( 'experiment variant has experiment relationship', function (): void {
	$variant = new ExperimentVariant();

	expect( $variant->experiment() )->toBeInstanceOf( BelongsTo::class );
} );

test( 'experiment variant control scope filters correctly', function (): void {
	ExperimentVariant::create( [
		'experiment_id' => $this->experiment->id,
		'name'          => 'Control',
		'is_control'    => true,
		'content_data'  => [],
	] );

	ExperimentVariant::create( [
		'experiment_id' => $this->experiment->id,
		'name'          => 'Variant B',
		'is_control'    => false,
		'content_data'  => [],
	] );

	expect( ExperimentVariant::control()->count() )->toBe( 1 );
} );

test( 'experiment variant calculates conversion rate', function (): void {
	$variant = ExperimentVariant::create( [
		'experiment_id' => $this->experiment->id,
		'name'          => 'Rate Test',
		'content_data'  => [],
		'impressions'   => 1000,
		'conversions'   => 50,
	] );

	expect( $variant->conversion_rate )->toBe( 5.0 );
} );

test( 'experiment variant conversion rate is zero with no impressions', function (): void {
	$variant = ExperimentVariant::create( [
		'experiment_id' => $this->experiment->id,
		'name'          => 'Zero Test',
		'content_data'  => [],
		'impressions'   => 0,
		'conversions'   => 0,
	] );

	expect( $variant->conversion_rate )->toBe( 0.0 );
} );
