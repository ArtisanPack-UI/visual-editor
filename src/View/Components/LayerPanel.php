<?php

/**
 * Layer Panel Component.
 *
 * Provides a List View (block tree) and Outline (heading structure)
 * for the Layers tab in the left sidebar.
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
 * Layer Panel component with List View and Outline sub-tabs.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class LayerPanel extends Component
{
	/**
	 * Valid view options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const VIEWS = [
		'list',
		'outline',
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
	 * @param string|null $id         Optional custom ID.
	 * @param string|null $label      Accessible label. Defaults to translation.
	 * @param string      $activeView Initially active view: list or outline.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $activeView = 'list',
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->activeView, self::VIEWS, true ) ) {
			$this->activeView = 'list';
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
		return view( 'visual-editor::components.layer-panel' );
	}
}
