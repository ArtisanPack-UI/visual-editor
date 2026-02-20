<?php

/**
 * Unit Control Component.
 *
 * A value input paired with a unit dropdown (px, em, rem, %, vw, vh).
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
 * Unit Control component for value + unit selection.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class UnitControl extends Component
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
	 * @param string|null          $id        Optional custom ID.
	 * @param string|null          $label     Label text.
	 * @param float|int|string|null $value    Current value.
	 * @param string               $unit      Current unit.
	 * @param array                $units     Available units.
	 * @param float|int|string|null $min      Minimum value.
	 * @param float|int|string|null $max      Maximum value.
	 * @param float|int|string     $step      Step increment.
	 * @param string|null          $hint      Hint text.
	 * @param string|null          $hintClass CSS class for hint.
	 * @param string|null          $errorField Error field name override.
	 * @param string|null          $errorClass CSS class for errors.
	 * @param bool|null            $omitError Whether to omit error display.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public string|int|float|null $value = null,
		public string $unit = 'px',
		public array $units = [ 'px', 'em', 'rem', '%', 'vw', 'vh' ],
		public string|int|float|null $min = null,
		public string|int|float|null $max = null,
		public string|int|float $step = 1,
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
		public ?string $errorField = null,
		public ?string $errorClass = 'text-error',
		public ?bool $omitError = false,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . $id;
	}

	/**
	 * Get the unit options formatted for the select component.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{id: string, name: string}>
	 */
	public function unitOptions(): array
	{
		return array_map( function ( string $unit ): array {
			return [
				'id'   => $unit,
				'name' => $unit,
			];
		}, $this->units );
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
		return view( 'visual-editor::components.unit-control' );
	}
}
