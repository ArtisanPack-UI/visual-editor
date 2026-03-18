<?php

/**
 * Document Taxonomies Component.
 *
 * Multi-select / tag input bound to the editor store's meta bag
 * for taxonomy assignments. Accepts taxonomy options as a prop.
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
 * Document Taxonomies field component for the document panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentTaxonomies extends Component
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
	 * @param string|null              $id       Optional custom ID.
	 * @param string                   $metaKey  The meta key to bind to in the editor store.
	 * @param string                   $taxonomy The taxonomy slug (e.g. 'category', 'tag').
	 * @param string|null              $label    Label text for the field.
	 * @param array<int|string, mixed> $options  Available taxonomy options as value => label pairs.
	 */
	public function __construct(
		public ?string $id = null,
		public string $metaKey = 'taxonomies',
		public string $taxonomy = '',
		public ?string $label = null,
		public array $options = [],
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
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
		return view( 'visual-editor::components.document-taxonomies' );
	}
}
