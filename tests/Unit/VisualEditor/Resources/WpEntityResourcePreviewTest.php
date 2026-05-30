<?php

declare( strict_types=1 );

/**
 * Unit tests for the editor-preview envelope on {@see WpEntityResource}
 * added in #483 — exercises the author / featured-image / formatted-date
 * shape that `useQueryPreview`'s `mapWpEntityToPost()` reads on the
 * client.
 *
 * Uses an anonymous Eloquent model subclass with stubbed `author` /
 * `featured_image_id` / `published_at` accessors so the assertions
 * don't depend on cms-framework or a real `users` table.
 */

use ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\PostResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

beforeEach( function (): void {
	Carbon::setLocale( 'en' );
} );

/**
 * Build a stub Eloquent model that pretends to expose the relations /
 * accessors `WpEntityResource::previewEnvelope()` reads. Anonymous
 * subclass so we can override property access without polluting the
 * fixtures namespace.
 */
function previewStubModel( array $attributes = [], ?object $author = null ): Model
{
	$model = new class extends Model {
		public ?object $stubAuthor = null;

		protected $guarded = [];

		// Pretend the model lives on a real table so `getKey()` works.
		protected $table = 'preview_stub';

		public function getAttribute( $key )
		{
			if ( 'author' === $key ) {
				return $this->stubAuthor;
			}

			return parent::getAttribute( $key );
		}

		public function __get( $key )
		{
			if ( 'author' === $key ) {
				return $this->stubAuthor;
			}

			return parent::__get( $key );
		}
	};

	$model->setRawAttributes( array_merge( [ 'id' => 1 ], $attributes ), true );
	$model->stubAuthor = $author;

	return $model;
}

it( 'returns a null `_preview` envelope when no author / featured-media / date is set', function () {
	$model = previewStubModel();

	$array = ( new PostResource( $model ) )->toArray( Request::create( '/' ) );

	expect( $array )->toHaveKey( '_preview' );
	expect( $array['_preview']['author'] )->toBeNull();
	expect( $array['_preview']['featuredImage'] )->toBeNull();
	expect( $array['_preview']['dateFormatted'] )->toBeNull();
} );

it( 'resolves the author envelope from the model\'s `author` accessor', function () {
	$author = (object) [
		'name'       => 'Jane Doe',
		'bio'        => 'Writer',
		'url'        => 'https://example.test/jane',
		'avatar_url' => 'https://example.test/avatar.jpg',
	];

	$model = previewStubModel( [], $author );

	$array = ( new PostResource( $model ) )->toArray( Request::create( '/' ) );

	expect( $array['_preview']['author'] )->toMatchArray( [
		'name'      => 'Jane Doe',
		'bio'       => 'Writer',
		'url'       => 'https://example.test/jane',
		'avatarUrl' => 'https://example.test/avatar.jpg',
	] );
} );

it( 'falls back to `description` / `website` when the author exposes those keys', function () {
	$author = (object) [
		'name'        => 'Jane Doe',
		'description' => 'Long-form description',
		'website'     => 'https://example.test/jane',
	];

	$model = previewStubModel( [], $author );

	$array = ( new PostResource( $model ) )->toArray( Request::create( '/' ) );

	expect( $array['_preview']['author']['bio'] )->toBe( 'Long-form description' );
	expect( $array['_preview']['author']['url'] )->toBe( 'https://example.test/jane' );
} );

it( 'formats the post date for display in `dateFormatted`', function () {
	$model = previewStubModel( [
		'published_at' => '2026-04-30 12:00:00',
	] );

	$array = ( new PostResource( $model ) )->toArray( Request::create( '/' ) );

	expect( $array['_preview']['dateFormatted'] )->toBe( 'April 30, 2026' );
} );

it( 'returns a null featured image when no media-library helpers are bound', function () {
	$model = previewStubModel( [
		'featured_image_id' => 42,
	] );

	$array = ( new PostResource( $model ) )->toArray( Request::create( '/' ) );

	// `apGetMediaUrl()` isn't defined in the unit-test scope, so the
	// envelope short-circuits to null rather than throwing.
	expect( $array['_preview']['featuredImage'] )->toBeNull();
} );
