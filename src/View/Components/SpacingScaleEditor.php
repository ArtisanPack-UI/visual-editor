<?php

/**
 * Spacing Scale Editor Component.
 *
 * Provides a visual editor interface for managing the global spacing scale,
 * including editing step values, applying presets, configuring block gap,
 * and adding custom spacing steps.
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
 * Spacing Scale Editor component for the global styles panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class SpacingScaleEditor extends Component
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
	 * The spacing data for JavaScript initialization.
	 *
	 * @since 1.0.0
	 *
	 * @var array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}
	 */
	public array $spacingData;

	/**
	 * The default spacing data for the reset button.
	 *
	 * @since 1.0.0
	 *
	 * @var array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}
	 */
	public array $defaultData;

	/**
	 * Base spacing values for override mode.
	 *
	 * When set, the editor operates in override mode where values
	 * matching the base are shown as inherited and values differing
	 * are shown as overridden.
	 *
	 * @since 1.0.0
	 *
	 * @var array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}|null
	 */
	public ?array $baseValues;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id         Optional custom ID.
	 * @param array|null  $spacing    Optional spacing data (defaults to manager data).
	 * @param array|null  $baseValues Optional base spacing for override mode.
	 */
	public function __construct(
		public ?string $id = null,
		?array $spacing = null,
		?array $baseValues = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		$resolvedManager   = app( 'visual-editor.spacing-scale' );
		$this->defaultData = $resolvedManager->toStoreFormat();

		if ( null !== $spacing ) {
			$manager = clone $resolvedManager;
			$manager->fromStoreFormat( $spacing );
		} else {
			$manager = $resolvedManager;
		}

		$this->spacingData = $manager->toStoreFormat();
		$this->baseValues  = $baseValues;
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
		return view( 'visual-editor::components.spacing-scale-editor' );
	}
}
