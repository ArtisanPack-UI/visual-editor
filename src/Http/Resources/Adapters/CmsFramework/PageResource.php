<?php

/**
 * PageResource — WP-shape `/wp/v2/pages` envelope.
 *
 * Layers `parent`, `menu_order`, and `template` onto the
 * {@see WpEntityResource} base envelope when the underlying model
 * exposes those columns (cms-framework's `Page` does; a host
 * fixture might not). Models that don't define them are unaffected
 * — the keys are omitted from the response.
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

class PageResource extends WpEntityResource
{
	protected function type(): string
	{
		return 'page';
	}

	/**
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function extraFields( Model $model ): array
	{
		$extras = [];

		if ( $this->hasAttribute( $model, 'parent_id' ) ) {
			$extras['parent'] = $this->intField( $model, [ 'parent_id', 'parent' ] );
		}

		if ( $this->hasAttribute( $model, 'menu_order' ) ) {
			$extras['menu_order'] = $this->intField( $model, [ 'menu_order' ] ) ?? 0;
		}

		if ( $this->hasAttribute( $model, 'template' ) ) {
			$extras['template'] = $this->stringField( $model, 'template' );
		}

		return $extras;
	}

	/**
	 * Returns true when the model has a column matching `$key` —
	 * either declared via `$fillable`/`$casts` or already loaded into
	 * the row's attributes. Fixtures that don't define the column at
	 * all (e.g. `TestBlockContentPageModel`) trip the false branch and
	 * the field is omitted from the envelope.
	 *
	 * @since 1.0.0
	 */
	protected function hasAttribute( Model $model, string $key ): bool
	{
		if ( in_array( $key, $model->getFillable(), true ) ) {
			return true;
		}

		if ( array_key_exists( $key, $model->getCasts() ) ) {
			return true;
		}

		return $model->exists && array_key_exists( $key, $model->getAttributes() );
	}
}
