<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;

uses( TestCase::class, WithCmsFramework::class );

it( 'boots cms-framework alongside visual-editor and exposes the Template model', function (): void {
	$template = Template::create( [
		'theme'         => 'digital-shopfront',
		'slug'          => 'single',
		'title'         => 'Single',
		'description'   => 'Single post template.',
		'status'        => 'publish',
		'is_custom'     => false,
		'block_content' => '<!-- wp:post-content /-->',
		'author_id'     => null,
	] );

	$fresh = $template->fresh();

	expect( $fresh )->not->toBeNull()
		->and( $fresh->slug )->toBe( 'single' )
		->and( $fresh->theme )->toBe( 'digital-shopfront' );
} );
