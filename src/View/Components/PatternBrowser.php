<?php

/**
 * Pattern Browser Component.
 *
 * Compact pattern listing for the Patterns tab in the left sidebar.
 * Provides search, category filter chips, and a pattern list.
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
 * Pattern Browser component for listing and searching patterns.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class PatternBrowser extends Component
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
	 * @param string|null  $id         Optional custom ID.
	 * @param array<mixed> $patterns   Available patterns.
	 * @param array<mixed> $categories Pattern categories.
	 * @param bool         $showSearch Whether to show the search input.
	 */
	public function __construct(
		public ?string $id = null,
		public array $patterns = [],
		public array $categories = [],
		public bool $showSearch = true,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( empty( $this->categories ) ) {
			$this->categories = self::getDefaultCategories();
		}
	}

	/**
	 * Get the default pattern categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function getDefaultCategories(): array
	{
		return [
			[ 'slug' => 'text', 'label' => __( 'visual-editor::ve.pattern_category_text' ) ],
			[ 'slug' => 'header', 'label' => __( 'visual-editor::ve.pattern_category_header' ) ],
			[ 'slug' => 'footer', 'label' => __( 'visual-editor::ve.pattern_category_footer' ) ],
			[ 'slug' => 'call-to-action', 'label' => __( 'visual-editor::ve.pattern_category_cta' ) ],
			[ 'slug' => 'gallery', 'label' => __( 'visual-editor::ve.pattern_category_gallery' ) ],
			[ 'slug' => 'testimonial', 'label' => __( 'visual-editor::ve.pattern_category_testimonial' ) ],
		];
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
		return view( 'visual-editor::components.pattern-browser' );
	}
}
