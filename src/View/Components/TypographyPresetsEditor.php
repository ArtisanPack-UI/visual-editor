<?php

/**
 * Typography Presets Editor Component.
 *
 * Provides a visual editor interface for managing global typography presets,
 * including font families, element styles, and type scale configuration.
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
 * Typography Presets Editor component for the global styles panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TypographyPresetsEditor extends Component
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
	 * The typography data for JavaScript initialization.
	 *
	 * @since 1.0.0
	 *
	 * @var array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}
	 */
	public array $typographyData;

	/**
	 * The default typography data for the reset button.
	 *
	 * @since 1.0.0
	 *
	 * @var array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}
	 */
	public array $defaultData;

	/**
	 * Available fonts grouped by slot category.
	 *
	 * Each slot (heading, body, mono) maps to an associative array
	 * of CSS family stack => display name, from getFontOptions().
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, string>>
	 */
	public array $availableFonts;

	/**
	 * Base typography values for override mode.
	 *
	 * When set, the editor operates in override mode where values
	 * matching the base are shown as inherited and values differing
	 * are shown as overridden.
	 *
	 * @since 1.0.0
	 *
	 * @var array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}|null
	 */
	public ?array $baseValues;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id         Optional custom ID.
	 * @param array|null  $typography Optional typography data (defaults to manager data).
	 * @param array|null  $baseValues Optional base typography for override mode.
	 */
	public function __construct(
		public ?string $id = null,
		?array $typography = null,
		?array $baseValues = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		$resolvedManager   = app( 'visual-editor.typography-presets' );
		$this->defaultData = $resolvedManager->toStoreFormat();

		if ( null !== $typography ) {
			$manager = clone $resolvedManager;
			$manager->fromStoreFormat( $typography );
		} else {
			$manager = $resolvedManager;
		}

		$this->typographyData = $manager->toStoreFormat();

		$slotCategories = [
			'heading' => 'heading',
			'body'    => 'body',
			'mono'    => null,
		];

		$this->availableFonts = [];

		foreach ( $slotCategories as $slot => $category ) {
			$this->availableFonts[ $slot ] = $resolvedManager->getFontOptions( $category );
		}

		$this->baseValues = $baseValues;
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
		return view( 'visual-editor::components.typography-presets-editor' );
	}
}
