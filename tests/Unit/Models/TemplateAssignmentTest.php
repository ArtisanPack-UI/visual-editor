<?php

/**
 * TemplateAssignment Model Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Models
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Models\TemplateAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses( RefreshDatabase::class );

beforeEach( function (): void {
	$this->artisan( 'migrate', [ '--database' => 'testbench' ] );
} );

it( 'has fillable attributes', function (): void {
	$assignment = new TemplateAssignment();

	expect( $assignment->getFillable() )->toContain(
		'content_type',
		'template_id',
		'user_id',
	);
} );

it( 'casts template_id and user_id to integer', function (): void {
	$template = Template::create( [
		'name'    => 'Test Template',
		'slug'    => 'test-template',
		'content' => [],
	] );

	$assignment = TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template->id,
	] );

	$assignment->refresh();

	expect( $assignment->template_id )->toBeInt()
		->and( $assignment->user_id )->toBeNull();
} );

it( 'enforces unique content type', function (): void {
	$template1 = Template::create( [
		'name'    => 'Test Template',
		'slug'    => 'test-template',
		'content' => [],
	] );

	$template2 = Template::create( [
		'name'    => 'Other Template',
		'slug'    => 'other-template',
		'content' => [],
	] );

	TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template1->id,
	] );

	expect( fn () => TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template2->id,
	] ) )->toThrow( Illuminate\Database\QueryException::class );
} );

it( 'belongs to a template', function (): void {
	$template = Template::create( [
		'name'    => 'Assigned Template',
		'slug'    => 'assigned-template',
		'content' => [],
	] );

	$assignment = TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template->id,
	] );

	expect( $assignment->template )->toBeInstanceOf( Template::class )
		->and( $assignment->template->id )->toBe( $template->id );
} );

it( 'stores user_id when provided', function (): void {
	DB::table( 'users' )->insert( [
		'id'    => 1,
		'name'  => 'Test User',
		'email' => 'test@test.com',
	] );

	$template = Template::create( [
		'name'    => 'Test Template',
		'slug'    => 'test-template',
		'content' => [],
	] );

	$assignment = TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template->id,
		'user_id'      => 1,
	] );

	$assignment->refresh();

	expect( $assignment->user_id )->toBe( 1 );
} );

it( 'cascades delete when template is deleted', function (): void {
	$template = Template::create( [
		'name'    => 'Deletable Template',
		'slug'    => 'deletable-template',
		'content' => [],
	] );

	TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template->id,
	] );

	$template->delete();

	expect( TemplateAssignment::where( 'content_type', 'post' )->exists() )->toBeFalse();
} );

it( 'filters by content type scope', function (): void {
	$template1 = Template::create( [
		'name'    => 'Template 1',
		'slug'    => 'template-1',
		'content' => [],
	] );

	$template2 = Template::create( [
		'name'    => 'Template 2',
		'slug'    => 'template-2',
		'content' => [],
	] );

	TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template1->id,
	] );

	TemplateAssignment::create( [
		'content_type' => 'page',
		'template_id'  => $template2->id,
	] );

	$postAssignments = TemplateAssignment::forContentType( 'post' )->get();

	expect( $postAssignments )->toHaveCount( 1 )
		->and( $postAssignments->first()->content_type )->toBe( 'post' );
} );

it( 'nullifies user_id when user is deleted', function (): void {
	DB::table( 'users' )->insert( [
		'id'    => 1,
		'name'  => 'Test User',
		'email' => 'test@test.com',
	] );

	$template = Template::create( [
		'name'    => 'Test Template',
		'slug'    => 'test-template',
		'content' => [],
	] );

	TemplateAssignment::create( [
		'content_type' => 'post',
		'template_id'  => $template->id,
		'user_id'      => 1,
	] );

	DB::table( 'users' )->where( 'id', 1 )->delete();

	$assignment = TemplateAssignment::where( 'content_type', 'post' )->first();

	expect( $assignment )->not->toBeNull()
		->and( $assignment->user_id )->toBeNull();
} );
