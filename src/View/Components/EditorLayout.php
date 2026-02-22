<?php

/**
 * Editor Layout Component.
 *
 * The top-level layout shell for the visual editor, arranging
 * the toolbar, canvas, sidebar, and status bar.
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
 * Editor Layout component for the overall editor shell.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class EditorLayout extends Component
{
	/**
	 * Valid sidebar positions.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SIDEBAR_POSITIONS = [
		'right',
		'left',
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
	 * @param string|null $id                 Optional custom ID.
	 * @param string|null $label              Accessible label. Defaults to translation.
	 * @param string      $sidebarPosition    Sidebar position: right or left.
	 * @param string      $sidebarWidth       CSS width for the sidebar.
	 * @param bool        $sidebarCollapsible Whether the sidebar can be collapsed.
	 * @param string      $leftSidebarWidth   CSS width for the left sidebar (inserter panel).
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $sidebarPosition = 'right',
		public string $sidebarWidth = '280px',
		public bool $sidebarCollapsible = true,
		public string $leftSidebarWidth = '280px',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->sidebarPosition, self::SIDEBAR_POSITIONS, true ) ) {
			$this->sidebarPosition = 'right';
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
		return view( 'visual-editor::components.editor-layout' );
	}
}
