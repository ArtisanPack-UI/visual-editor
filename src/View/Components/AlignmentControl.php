<?php

/**
 * Alignment Control Component.
 *
 * A button group for alignment selection supporting horizontal, vertical, and matrix modes.
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
 * Alignment Control component for horizontal, vertical, and matrix alignment.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class AlignmentControl extends Component
{

	/**
	 * The default horizontal alignment options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const HORIZONTAL_OPTIONS = [ 'left', 'center', 'right', 'justify' ];

	/**
	 * The default vertical alignment options.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const VERTICAL_OPTIONS = [ 'top', 'center', 'bottom' ];

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
	 * @param string|null $id      Optional custom ID.
	 * @param string|null $label   Label text.
	 * @param string|null $value   Currently selected alignment.
	 * @param string      $mode    Alignment mode: horizontal, vertical, or matrix.
	 * @param array|null  $options Custom options override.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $value = null,
		public string $mode = 'horizontal',
		public ?array $options = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . $id;
	}

	/**
	 * Get the options based on the current mode.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function resolvedOptions(): array
	{
		if ( null !== $this->options ) {
			return $this->options;
		}

		return match ( $this->mode ) {
			'vertical' => self::VERTICAL_OPTIONS,
			default    => self::HORIZONTAL_OPTIONS,
		};
	}

	/**
	 * Get the matrix options as a 3x3 grid.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{horizontal: string, vertical: string, value: string}>
	 */
	public function matrixOptions(): array
	{
		$matrix      = [];
		$verticals   = [ 'top', 'center', 'bottom' ];
		$horizontals = [ 'left', 'center', 'right' ];

		foreach ( $verticals as $vertical ) {
			foreach ( $horizontals as $horizontal ) {
				$matrix[] = [
					'horizontal' => $horizontal,
					'vertical'   => $vertical,
					'value'      => $vertical . '-' . $horizontal,
				];
			}
		}

		return $matrix;
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
		return view( 'visual-editor::components.alignment-control' );
	}
}
