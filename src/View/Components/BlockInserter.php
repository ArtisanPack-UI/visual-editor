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
	 * Default SVG icon paths for core block types.
	 *
	 * Each value is a `<path>` element string to be placed inside an
	 * `<svg>` wrapper. Consumers can override rendering via `$iconRenderer`.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public const DEFAULT_ICONS = [
		'paragraph' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />',
		'heading'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.243 4.493v7.5m0 0v7.514m0-7.514h10.5m0-7.5v7.5m0 0v7.514m4.014-1.5 2.25-2.25m0 0 2.25-2.25m-2.25 2.25-2.25-2.25m2.25 2.25 2.25 2.25" />',
		'list'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />',
		'quote'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />',
		'image'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />',
		'gallery'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" />',
		'video'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />',
		'audio'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />',
		'file'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />',
		'columns'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
		'column'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5v15m6-15v15M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />',
		'group'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />',
		'spacer'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />',
		'divider'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />',
		'button'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672Zm-7.518-.267A8.25 8.25 0 1 1 20.25 10.5M8.288 14.212A5.25 5.25 0 1 1 17.25 10.5" />',
		'code'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />',
		'separator' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />',
		'table'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375" />',
		'_default'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Z" />',
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
	 * @param string|null    $id                 Optional custom ID.
	 * @param string         $mode               Display mode: panel or inline.
	 * @param array<mixed>   $blocks             Registered block definitions.
	 * @param array<string>  $categories         Category slugs.
	 * @param bool           $showSearch         Whether to show the search input.
	 * @param bool           $showCategories     Whether to show category filter tabs.
	 * @param bool           $showRecentlyUsed   Whether to show recently used section.
	 * @param int            $recentlyUsedMax    Maximum number of recent blocks to show.
	 * @param bool           $enableDragToInsert Whether blocks can be dragged from inserter.
	 * @param int|null       $insertAt           Target insertion index for inline mode.
	 * @param Closure|null   $iconRenderer       Optional closure to render icons. Receives icon name string, returns HTML.
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
		public ?Closure $iconRenderer = null,
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
	 * Resolve block icons into rendered HTML strings.
	 *
	 * If an `$iconRenderer` closure is provided, it is called for each block's
	 * icon name. Otherwise, the default SVG path map is used as a fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of block type to rendered icon HTML.
	 */
	public function resolveBlockIcons(): array
	{
		$icons = [];

		if ( $this->iconRenderer ) {
			$renderer = $this->iconRenderer;

			foreach ( $this->blocks as $block ) {
				$blockName = $block['name'] ?? '';
				$iconName  = $block['icon'] ?? $blockName;

				if ( $blockName ) {
					$icons[ $blockName ] = $renderer( $iconName );
				}
			}

			$icons['_default'] = $renderer( '_default' );
		}

		foreach ( self::DEFAULT_ICONS as $type => $pathHtml ) {
			if ( ! isset( $icons[ $type ] ) ) {
				$icons[ $type ] = '<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">' . $pathHtml . '</svg>';
			}
		}

		return $icons;
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
