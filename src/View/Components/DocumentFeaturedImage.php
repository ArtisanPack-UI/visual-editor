<?php

/**
 * Document Featured Image Component.
 *
 * Image picker bound to the editor store's meta bag for the featured image.
 * Shows a thumbnail preview when an image is set. Integrates with the
 * artisanpack-ui/media-library MediaModal when available.
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

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Document Featured Image field component for the document panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentFeaturedImage extends Component
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
	 * Whether the media library package is available.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $hasMediaLibrary;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id      Optional custom ID.
	 * @param string      $metaKey The meta key to bind to in the editor store.
	 * @param string|null $label   Label text for the field.
	 */
	public function __construct(
		public ?string $id = null,
		public string $metaKey = 'featured_image',
		public ?string $label = null,
	) {
		$this->uuid            = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
		$this->hasMediaLibrary = class_exists( \ArtisanPackUI\MediaLibrary\MediaLibraryServiceProvider::class );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.document-featured-image' );
	}
}
