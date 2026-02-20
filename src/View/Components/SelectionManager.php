<?php

/**
 * Selection Manager Component.
 *
 * Manages block selection state and clipboard operations
 * (copy, cut, paste, duplicate) with keyboard shortcut integration.
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
 * Selection Manager component for block selection and clipboard handling.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class SelectionManager extends Component
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
	 * @param string|null $id              Optional custom ID.
	 * @param bool        $multiSelect     Whether multi-selection is enabled.
	 * @param bool        $enableClipboard Whether clipboard operations are enabled.
	 * @param string      $selectionClass  CSS class applied to selected blocks.
	 * @param string      $multiSelectionClass CSS class for multi-selected blocks.
	 * @param string      $cutClass        CSS class applied to cut blocks.
	 */
	public function __construct(
		public ?string $id = null,
		public bool $multiSelect = true,
		public bool $enableClipboard = true,
		public string $selectionClass = 'ring-2 ring-primary',
		public string $multiSelectionClass = 'ring-2 ring-primary/60',
		public string $cutClass = 'opacity-50',
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
		return view( 'visual-editor::components.selection-manager' );
	}
}
