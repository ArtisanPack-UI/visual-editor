<?php

/**
 * Document Title Component.
 *
 * Text input bound to the editor store's meta bag for the document title.
 * Optionally auto-generates a slug on change.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Document Title field component for the document panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentTitle extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id          Optional custom ID.
	 * @param string      $metaKey     The meta key to bind to in the editor store.
	 * @param string|null $label       Label text for the field.
	 * @param string|null $placeholder Placeholder text for the input.
	 * @param bool        $autoSlug    Whether to auto-generate a slug on change. When used alongside DocumentPermalink, ensure slugKey differs from DocumentPermalink's metaKey to avoid collisions.
	 * @param string      $slugKey     The meta key for the auto-generated slug. Defaults to 'slug' which shares DocumentPermalink's default metaKey — customize one when using both components.
	 */
	public function __construct(
		public ?string $id = null,
		public string $metaKey = 'title',
		public ?string $label = null,
		public ?string $placeholder = null,
		public bool $autoSlug = false,
		public string $slugKey = 'slug',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return string|View
	 */
	public function render(): View|string
	{
		return view( 'visual-editor::components.document-title' );
	}
}
