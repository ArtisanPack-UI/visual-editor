<?php

/**
 * Graceful Degradation Tests.
 *
 * Verifies that the site editor works without the CMS framework installed.
 * When gates are not defined, routes should be accessible with just base middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Authorization
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Livewire\SiteEditor\HubPage;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses( RefreshDatabase::class );

/**
 * Create a test user for graceful degradation tests.
 *
 * @return Authenticatable
 */
function createDegradationTestUser(): Authenticatable
{
	$email = 'degrade-test-' . Illuminate\Support\Str::random( 6 ) . '@example.com';

	$id = DB::table( 'users' )->insertGetId( [
		'name'       => 'Degradation Test User',
		'email'      => $email,
		'created_at' => now(),
		'updated_at' => now(),
	] );

	$user        = new class () extends Authenticatable {
		protected $table = 'users';
	};
	$user->id    = $id;
	$user->name  = 'Degradation Test User';
	$user->email = $email;

	return $user;
}

test( 'hub route accessible when access permission gate is not registered', function (): void {
	$user = createDegradationTestUser();

	$this->actingAs( $user )
		->get( route( 'visual-editor.site-editor' ) )
		->assertSuccessful();
} );

test( 'hub page shows all cards when no gates are defined', function (): void {
	config( [ 'artisanpack.visual-editor.site_editor.gates' => [] ] );

	$component = Livewire::test( HubPage::class );

	$component->assertSee( __( 'visual-editor::ve.hub_global_styles' ) )
		->assertSee( __( 'visual-editor::ve.hub_templates' ) )
		->assertSee( __( 'visual-editor::ve.hub_template_parts' ) )
		->assertSee( __( 'visual-editor::ve.hub_patterns' ) );
} );

test( 'hub page shows all cards when gates defined but not registered', function (): void {
	config( [
		'artisanpack.visual-editor.site_editor.gates' => [
			'styles'    => 'visual-editor.manage-styles',
			'templates' => 'visual-editor.manage-templates',
			'parts'     => 'visual-editor.manage-parts',
			'patterns'  => 'visual-editor.manage-patterns',
		],
	] );

	$component = Livewire::test( HubPage::class );

	$component->assertSee( __( 'visual-editor::ve.hub_global_styles' ) )
		->assertSee( __( 'visual-editor::ve.hub_templates' ) )
		->assertSee( __( 'visual-editor::ve.hub_template_parts' ) )
		->assertSee( __( 'visual-editor::ve.hub_patterns' ) );
} );

test( 'hub page filters cards when gates are registered and user lacks permission', function (): void {
	Gate::define( 'visual-editor.manage-styles', fn () => false );
	Gate::define( 'visual-editor.manage-templates', fn () => true );
	Gate::define( 'visual-editor.manage-parts', fn () => true );
	Gate::define( 'visual-editor.manage-patterns', fn () => false );

	$user = createDegradationTestUser();

	$component = Livewire::actingAs( $user )
		->test( HubPage::class );

	$component->assertDontSee( __( 'visual-editor::ve.hub_global_styles' ) )
		->assertSee( __( 'visual-editor::ve.hub_templates' ) )
		->assertSee( __( 'visual-editor::ve.hub_template_parts' ) )
		->assertDontSee( __( 'visual-editor::ve.hub_patterns' ) );
} );

test( 'hub page cards include permission key', function (): void {
	$component = new HubPage();
	$cards     = $component->getCards();

	foreach ( $cards as $card ) {
		expect( $card )->toHaveKey( 'permission' );
	}
} );

test( 'policies are registered for models', function (): void {
	expect( Gate::getPolicyFor( ArtisanPackUI\VisualEditor\Models\Template::class ) )
		->toBeInstanceOf( ArtisanPackUI\VisualEditor\Policies\TemplateSiteEditorPolicy::class );

	expect( Gate::getPolicyFor( ArtisanPackUI\VisualEditor\Models\TemplatePart::class ) )
		->toBeInstanceOf( ArtisanPackUI\VisualEditor\Policies\TemplatePartSiteEditorPolicy::class );

	expect( Gate::getPolicyFor( ArtisanPackUI\VisualEditor\Models\Pattern::class ) )
		->toBeInstanceOf( ArtisanPackUI\VisualEditor\Policies\PatternSiteEditorPolicy::class );
} );

test( 'config includes gates array', function (): void {
	$gates = config( 'artisanpack.visual-editor.site_editor.gates' );

	expect( $gates )->toBeArray();
	expect( $gates )->toHaveKey( 'access' );
	expect( $gates )->toHaveKey( 'styles' );
	expect( $gates )->toHaveKey( 'templates' );
	expect( $gates )->toHaveKey( 'parts' );
	expect( $gates )->toHaveKey( 'patterns' );
	expect( $gates )->toHaveKey( 'template_styles' );
	expect( $gates )->toHaveKey( 'lock_content' );
} );
