<?php

/**
 * Block Alignment Control Component.
 *
 * A dropdown control for block-level alignment selection (none, left, center, right, wide, full).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Block Alignment Control for block-level positioning (none, left, center, right, wide, full).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class BlockAlignmentControl extends Component
{

	/**
	 * All possible block alignment values.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALL_OPTIONS = [ 'none', 'left', 'center', 'right', 'wide', 'full' ];

	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * The resolved options with 'none' always present.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	public array $resolvedOptions;

	/**
	 * Create a new component instance.
	 *
	 * @since 2.0.0
	 *
	 * @param string      $value   Currently selected block alignment.
	 * @param array       $options Available alignment options for this block type.
	 * @param string|null $label   Accessible label.
	 */
	public function __construct(
		public string $value = 'none',
		public array $options = [],
		public ?string $label = null,
	) {
		$this->uuid            = 've-ba-' . Str::random( 8 );
		$this->resolvedOptions = $this->resolveOptions();
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 2.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.block-alignment-control' );
	}

	/**
	 * Resolve the options, ensuring 'none' is always present and first.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string>
	 */
	protected function resolveOptions(): array
	{
		$options = $this->options;

		if ( ! in_array( 'none', $options, true ) ) {
			array_unshift( $options, 'none' );
		}

		return $options;
	}
}
