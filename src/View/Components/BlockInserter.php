<?php

/**
 * Block Inserter Component.
 *
 * A searchable, categorized block library for adding new blocks
 * to the editor. Supports panel (sidebar) and inline (popover) modes.
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
 * Block Inserter component for adding blocks to the editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class BlockInserter extends Component
{
	/**
	 * Valid display modes.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const MODES = [
		'panel',
		'inline',
	];

	/**
	 * Default block categories.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_CATEGORIES = [
		'text',
		'media',
		'layout',
		'interactive',
		'embed',
		'dynamic',
	];

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
	 * @param string|null    $id                Optional custom ID.
	 * @param string         $mode              Display mode: panel or inline.
	 * @param array<mixed>   $blocks            Registered block definitions.
	 * @param array<string>  $categories        Category slugs.
	 * @param bool           $showSearch        Whether to show the search input.
	 * @param bool           $showCategories    Whether to show category filter tabs.
	 * @param bool           $showRecentlyUsed  Whether to show recently used section.
	 * @param int            $recentlyUsedMax   Maximum number of recent blocks to show.
	 * @param bool           $enableDragToInsert Whether blocks can be dragged from inserter.
	 * @param int|null       $insertAt          Target insertion index for inline mode.
	 */
	public function __construct(
		public ?string $id = null,
		public string $mode = 'panel',
		public array $blocks = [],
		public array $categories = [],
		public bool $showSearch = true,
		public bool $showCategories = true,
		public bool $showRecentlyUsed = true,
		public int $recentlyUsedMax = 6,
		public bool $enableDragToInsert = true,
		public ?int $insertAt = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->mode, self::MODES, true ) ) {
			$this->mode = 'panel';
		}

		if ( 0 === count( $this->categories ) ) {
			$this->categories = self::DEFAULT_CATEGORIES;
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
		return view( 'visual-editor::components.block-inserter' );
	}
}
