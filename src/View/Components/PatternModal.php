<?php

/**
 * Pattern Modal Component.
 *
 * Full-screen modal for browsing and selecting patterns.
 * Uses a native HTML dialog element driven by Alpine.js.
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
 * Pattern Modal component for full-screen pattern browsing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class PatternModal extends Component
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
	 */
	public function __construct(
		public ?string $id = null,
		public array $patterns = [],
		public array $categories = [],
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( empty( $this->categories ) ) {
			$this->categories = PatternBrowser::getDefaultCategories();
		}
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
		return view( 'visual-editor::components.pattern-modal' );
	}
}
