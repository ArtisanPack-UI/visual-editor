<?php

/**
 * Keyboard Shortcuts Component.
 *
 * Provides a keyboard shortcuts system with registration, execution,
 * conflict detection, and a help modal displaying all shortcuts.
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
 * Keyboard shortcuts component for registering and displaying shortcuts.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class KeyboardShortcuts extends Component
{
	/**
	 * Default shortcut categories.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_CATEGORIES = [
		'global',
		'block',
		'selection',
		'navigation',
		'insertion',
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
	 * @param string|null $id             Optional custom ID.
	 * @param array       $shortcuts      Pre-registered shortcuts array.
	 * @param bool        $showHelpModal  Whether to render the help modal.
	 * @param string      $helpShortcut   Key to open help modal.
	 * @param string|null $title          Help modal title.
	 */
	public function __construct(
		public ?string $id = null,
		public array $shortcuts = [],
		public bool $showHelpModal = true,
		public string $helpShortcut = '?',
		public ?string $title = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( null === $this->title ) {
			$this->title = __( 'Keyboard Shortcuts' );
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
		return view( 'visual-editor::components.keyboard-shortcuts' );
	}
}
