<?php

/**
 * Template Part Slot Component.
 *
 * A visual placeholder in the template canvas representing a template
 * part area (header, footer, sidebar, custom). Shows the assigned part
 * content and provides an overlay for selecting or editing the part.
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

use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Template Part Slot component for template part area placeholders.
 *
 * Renders a bordered region in the template canvas that represents
 * where a template part (header, footer, sidebar) is placed. When
 * empty, shows a picker to select a part. When filled, shows the
 * part content with an "Edit Part" overlay button.
 *
 * Usage:
 *   <x-ve-template-part-slot
 *       area="header"
 *       :assigned-slug="$headerSlug"
 *       :available-parts="$headerParts"
 *   />
 *
 * @deprecated Will be replaced by a template-part block type in a future release.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TemplatePartSlot extends Component
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
	 * @param string|null $id             Optional custom ID.
	 * @param string|null $label          Accessible label. Defaults to area name.
	 * @param string      $area           The template part area: header, footer, sidebar, or custom.
	 * @param string|null $assignedSlug   The slug of the currently assigned template part.
	 * @param string|null $assignedName   The display name of the currently assigned template part.
	 * @param array<int, array<string, mixed>> $availableParts Available template parts for this area.
	 * @param bool        $isEditing      Whether this slot is currently being inline-edited.
	 * @param bool        $isLocked       Whether the assigned part is locked from editing.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string $area = 'custom',
		public ?string $assignedSlug = null,
		public ?string $assignedName = null,
		public array $availableParts = [],
		public bool $isEditing = false,
		public bool $isLocked = false,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		$validAreas = TemplatePart::AREAS;
		if ( ! in_array( $this->area, $validAreas, true ) ) {
			$this->area = 'custom';
		}
	}

	/**
	 * Check if a template part is assigned to this slot.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function hasAssignment(): bool
	{
		return null !== $this->assignedSlug && '' !== $this->assignedSlug;
	}

	/**
	 * Get the display label for this slot's area.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function areaLabel(): string
	{
		return match ( $this->area ) {
			'header'  => __( 'visual-editor::ve.template_area_header' ),
			'footer'  => __( 'visual-editor::ve.template_area_footer' ),
			'sidebar' => __( 'visual-editor::ve.template_area_sidebar' ),
			default   => __( 'visual-editor::ve.template_area_custom' ),
		};
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
		return view( 'visual-editor::components.template-part-slot' );
	}
}
