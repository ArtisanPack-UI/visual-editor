<?php

/**
 * Document Permalink Component.
 *
 * Slug input bound to the editor store's meta bag for the document permalink.
 * Shows a base URL prefix with an editable slug portion.
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
 * Document Permalink field component for the document panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentPermalink extends Component
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
	 * @param string|null $id      Optional custom ID.
	 * @param string      $metaKey The meta key to bind to in the editor store.
	 * @param string      $baseUrl The base URL prefix displayed before the slug.
	 * @param string|null $label   Label text for the field.
	 */
	public function __construct(
		public ?string $id = null,
		public string $metaKey = 'slug',
		public string $baseUrl = '/',
		public ?string $label = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . Str::slug( $id ) : '' );
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
		return view( 'visual-editor::components.document-permalink' );
	}
}
