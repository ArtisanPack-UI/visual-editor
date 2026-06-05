<?php

/**
 * PostResource — WP-shape `/wp/v2/posts` envelope.
 *
 * Adds `categories` and `tags` to the {@see WpEntityResource} base
 * envelope when the underlying model defines those relations
 * (cms-framework's `Post` does; a host fixture might not). Models
 * that don't define them are unaffected — the keys are omitted.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework;

use Illuminate\Database\Eloquent\Model;

class PostResource extends WpEntityResource
{
	protected function type(): string
	{
		return 'post';
	}

	/**
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function extraFields( Model $model ): array
	{
		$extras = [];

		if ( method_exists( $model, 'categories' ) ) {
			$extras['categories'] = $this->relationIds( $model, 'categories' );
		}

		if ( method_exists( $model, 'tags' ) ) {
			$extras['tags'] = $this->relationIds( $model, 'tags' );
		}

		return $extras;
	}
}
