<?php

/**
 * Panel Body Component.
 *
 * A collapsible section within a Panel with animated expand/collapse.
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
 * Panel Body component for collapsible inspector sections.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class PanelBody extends Component
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
	 * @param string|null $id          Optional custom ID.
	 * @param string|null $title       Section title.
	 * @param string|null $icon        Optional icon name for the header.
	 * @param bool        $opened      Whether the section starts open.
	 * @param bool        $collapsible Whether the section can be collapsed.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $title = null,
		public ?string $icon = null,
		public bool $opened = true,
		public bool $collapsible = true,
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
		return view( 'visual-editor::components.panel-body' );
	}
}
