<?php

/**
 * TemplatePartsCrud Livewire Component Feature Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Livewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\TemplatePartsCrud;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses( RefreshDatabase::class );

test( 'template parts crud renders headless component', function (): void {
	Livewire::test( TemplatePartsCrud::class )
		->assertStatus( 200 )
		->assertSee( 'hidden' );
} );

test( 'template parts crud creates a new part', function (): void {
	Livewire::test( TemplatePartsCrud::class )
		->dispatch( 've-template-part-create', area: 'header', name: 'Main Header' )
		->assertDispatched( 've-template-part-created' );

	$this->assertDatabaseHas( 'visual_editor_template_parts', [
		'name' => 'Main Header',
		'area' => 'header',
	] );
} );

test( 'template parts crud ignores empty name', function (): void {
	Livewire::test( TemplatePartsCrud::class )
		->dispatch( 've-template-part-create', area: 'header', name: '' )
		->assertNotDispatched( 've-template-part-created' );
} );

test( 'template parts crud handles duplicate slug', function (): void {
	TemplatePart::create( [
		'name'      => 'Existing Header',
		'slug'      => 'main-header',
		'area'      => 'header',
		'content'   => [],
		'status'    => 'active',
		'is_custom' => false,
	] );

	Livewire::test( TemplatePartsCrud::class )
		->dispatch( 've-template-part-create', area: 'header', name: 'Main Header' )
		->assertDispatched( 've-template-part-created' );

	// Should create with a modified slug.
	expect( TemplatePart::where( 'name', 'Main Header' )->exists() )->toBeTrue();
} );

test( 'template parts crud dispatches clear assignment event', function (): void {
	Livewire::test( TemplatePartsCrud::class )
		->dispatch( 've-template-part-clear-assignment', area: 'footer' )
		->assertDispatched( 've-template-part-assignment-cleared' );
} );
