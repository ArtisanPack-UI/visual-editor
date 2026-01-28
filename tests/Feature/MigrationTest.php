<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Schema;

test( 've_contents table exists', function (): void {
	expect( Schema::hasTable( 've_contents' ) )->toBeTrue();
} );

test( 've_content_revisions table exists', function (): void {
	expect( Schema::hasTable( 've_content_revisions' ) )->toBeTrue();
} );

test( 've_templates table exists', function (): void {
	expect( Schema::hasTable( 've_templates' ) )->toBeTrue();
} );

test( 've_template_parts table exists', function (): void {
	expect( Schema::hasTable( 've_template_parts' ) )->toBeTrue();
} );

test( 've_user_sections table exists', function (): void {
	expect( Schema::hasTable( 've_user_sections' ) )->toBeTrue();
} );

test( 've_global_styles table exists', function (): void {
	expect( Schema::hasTable( 've_global_styles' ) )->toBeTrue();
} );

test( 've_experiments table exists', function (): void {
	expect( Schema::hasTable( 've_experiments' ) )->toBeTrue();
} );

test( 've_experiment_variants table exists', function (): void {
	expect( Schema::hasTable( 've_experiment_variants' ) )->toBeTrue();
} );

test( 've_editor_locks table exists', function (): void {
	expect( Schema::hasTable( 've_editor_locks' ) )->toBeTrue();
} );

test( 've_contents table has expected columns', function (): void {
	$columns = Schema::getColumnListing( 've_contents' );

	expect( $columns )->toContain( 'id' )
		->and( $columns )->toContain( 'uuid' )
		->and( $columns )->toContain( 'content_type' )
		->and( $columns )->toContain( 'title' )
		->and( $columns )->toContain( 'slug' )
		->and( $columns )->toContain( 'sections' )
		->and( $columns )->toContain( 'status' )
		->and( $columns )->toContain( 'author_id' )
		->and( $columns )->toContain( 'deleted_at' );
} );

test( 've_content_revisions table has expected columns', function (): void {
	$columns = Schema::getColumnListing( 've_content_revisions' );

	expect( $columns )->toContain( 'id' )
		->and( $columns )->toContain( 'content_id' )
		->and( $columns )->toContain( 'user_id' )
		->and( $columns )->toContain( 'type' )
		->and( $columns )->toContain( 'data' )
		->and( $columns )->toContain( 'created_at' );
} );

test( 've_templates table has expected columns', function (): void {
	$columns = Schema::getColumnListing( 've_templates' );

	expect( $columns )->toContain( 'id' )
		->and( $columns )->toContain( 'slug' )
		->and( $columns )->toContain( 'name' )
		->and( $columns )->toContain( 'template_parts' )
		->and( $columns )->toContain( 'content_area_settings' )
		->and( $columns )->toContain( 'is_active' );
} );

test( 've_experiments table has expected columns', function (): void {
	$columns = Schema::getColumnListing( 've_experiments' );

	expect( $columns )->toContain( 'id' )
		->and( $columns )->toContain( 'content_id' )
		->and( $columns )->toContain( 'created_by' )
		->and( $columns )->toContain( 'goal_type' )
		->and( $columns )->toContain( 'status' )
		->and( $columns )->toContain( 'winner_variant_id' );
} );

test( 've_editor_locks table has expected columns', function (): void {
	$columns = Schema::getColumnListing( 've_editor_locks' );

	expect( $columns )->toContain( 'id' )
		->and( $columns )->toContain( 'content_id' )
		->and( $columns )->toContain( 'user_id' )
		->and( $columns )->toContain( 'session_id' )
		->and( $columns )->toContain( 'started_at' )
		->and( $columns )->toContain( 'last_heartbeat' );
} );
