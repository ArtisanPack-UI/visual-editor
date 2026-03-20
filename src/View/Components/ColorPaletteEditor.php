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
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id      Optional custom ID.
	 * @param array|null  $palette Optional palette to display (defaults to manager palette).
	 */
	public function __construct(
		public ?string $id = null,
		?array $palette = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( null !== $palette ) {
			$manager = clone app( 'visual-editor.color-palette' );
			$manager->fromStoreFormat( $palette );
		} else {
			$manager = app( 'visual-editor.color-palette' );
		}

		$this->paletteEntries = $manager->toStoreFormat();
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
