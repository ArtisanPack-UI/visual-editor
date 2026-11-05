<?php

/**
 * Content visibility mount wiring (#1030).
 *
 * Every editor mount stamps the registered content sources as
 * `data-visibility-content-sources`, so a source added through
 * `ap.visualEditor.visibility.registerContentSources` reaches the panel.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\Visibility\ContentSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\TestBlockContentModel;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
	] );
} );

/**
 * The attribute as Blade escapes it.
 */
function contentSourcesAttribute(): string
{
	$payload = app( ContentSourceRegistry::class )->toEditorPayload();

	return 'data-visibility-content-sources="'
		. e( json_encode( $payload, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) )
		. '"';
}

it( 'stamps the content sources onto the post-editor component', function (): void {
	$model = TestBlockContentModel::create( [
		'title'   => 'Sources',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render( '<x-visual-editor :model="$model" />', [ 'model' => $model ] );

	expect( $html )->toContain( contentSourcesAttribute() );
} );

it( 'stamps the content sources onto the standalone editor mount', function (): void {
	$this->withoutVite();

	$html = View::make( 'visual-editor::editor.mount', [
		'resource' => 'posts',
		'modelId'  => '1',
		'apiBase'  => '/visual-editor/api',
	] )->render();

	expect( $html )->toContain( contentSourcesAttribute() );
} );

it( 'stamps the content sources onto the site-editor mount', function (): void {
	$this->app->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate {
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );

	$this->withoutVite();

	$html = $this->get( '/visual-editor/site' )->assertOk()->getContent();

	expect( $html )->toContain( contentSourcesAttribute() );
} );
