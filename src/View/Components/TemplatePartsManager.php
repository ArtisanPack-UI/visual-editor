<?php

/**
 * Template Parts Manager Component.
 *
 * Provides a sidebar panel for managing template part assignments
 * per area (header, footer, sidebar, custom). Supports assigning
 * existing parts, creating new parts, and clearing assignments.
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

use ArtisanPackUI\VisualEditor\Services\TemplatePartManager;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Template Parts Manager component for the template editor sidebar.
 *
 * Displays template part areas with dropdowns for assigning existing
 * parts, buttons for creating new parts, and controls for editing
 * or clearing assignments.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TemplatePartsManager extends Component
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
	 * Available template parts grouped by area.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<int, array{id: int, name: string, slug: string}>>
	 */
	public array $partsByArea;

	/**
	 * The area labels for display.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public array $areaLabels;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null                        $id               Optional custom ID.
	 * @param array<string, int|null>            $assignments      Current part assignments keyed by area.
	 * @param array<string, array<string, mixed>> $availableParts  Optional pre-loaded parts by area.
	 */
	public function __construct(
		public ?string $id = null,
		public array $assignments = [],
		?array $availableParts = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		$this->areaLabels = [
			'header'  => __( 'visual-editor::ve.template_part_area_header' ),
			'footer'  => __( 'visual-editor::ve.template_part_area_footer' ),
			'sidebar' => __( 'visual-editor::ve.template_part_area_sidebar' ),
			'custom'  => __( 'visual-editor::ve.template_part_area_custom' ),
		];

		if ( null !== $availableParts ) {
			$this->partsByArea = $availableParts;
		} else {
			$this->partsByArea = $this->loadPartsByArea();
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
		return view( 'visual-editor::components.template-parts-manager' );
	}

	/**
	 * Load available template parts grouped by area via TemplatePartManager.
	 *
	 * Uses the service instead of querying the model directly so that
	 * in-memory registered parts and filter hooks are included.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, array{id: int|null, name: string, slug: string}>>
	 */
	protected function loadPartsByArea(): array
	{
		/** @var TemplatePartManager $manager */
		$manager     = app( 'visual-editor.template-parts' );
		$partsByArea = [];

		foreach ( array_keys( $this->areaLabels ) as $area ) {
			$parts = collect( $manager->forArea( $area ) )
				->map( fn ( array $part ): array => [
					'id'   => $part['id'] ?? null,
					'name' => $part['name'] ?? $part['slug'],
					'slug' => $part['slug'],
				] )
				->sortBy( 'name' )
				->values()
				->all();

			$partsByArea[ $area ] = $parts;
		}

		return $partsByArea;
	}
}
