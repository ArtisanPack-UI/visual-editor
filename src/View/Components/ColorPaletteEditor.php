<?php

/**
 * Color Palette Editor Component.
 *
 * Provides a visual editor interface for managing the global color palette,
 * including adding, editing, and removing colors with accessibility contrast checks.
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
 * Color Palette Editor component for the global styles panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class ColorPaletteEditor extends Component
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
	 * The palette entries for JavaScript initialization.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{name: string, slug: string, color: string}>
	 */
	public array $paletteEntries;

	/**
	 * The resolved default palette for the reset button.
	 *
	 * Uses the installation's resolved palette (config + filters)
	 * rather than the hardcoded constant, so project overrides
	 * are respected when resetting.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{name: string, slug: string, color: string}>
	 */
	public array $defaultEntries;

	/**
	 * Base palette values for override mode.
	 *
	 * When set, the editor operates in override mode where entries
	 * matching the base values are shown as inherited and entries
	 * differing from the base are shown as overridden.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{name: string, slug: string, color: string}>|null
	 */
	public ?array $baseValues;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id         Optional custom ID.
	 * @param array|null  $palette    Optional palette to display (defaults to manager palette).
	 * @param array|null  $baseValues Optional base palette for override mode.
	 */
	public function __construct(
		public ?string $id = null,
		?array $palette = null,
		?array $baseValues = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		$resolvedManager      = app( 'visual-editor.color-palette' );
		$this->defaultEntries = $resolvedManager->toStoreFormat();

		if ( null !== $palette ) {
			$manager = clone $resolvedManager;
			$manager->fromStoreFormat( $palette );
		} else {
			$manager = $resolvedManager;
		}

		$this->paletteEntries = $manager->toStoreFormat();
		$this->baseValues     = $baseValues;
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
		return view( 'visual-editor::components.color-palette-editor' );
	}
}
