<?php

/**
 * Shadow Control Component.
 *
 * Provides a shadow picker with Tailwind presets and custom CSS shadow input.
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
 * Shadow control component for the block inspector.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      2.0.0
 */
class ShadowControl extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Available shadow presets.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, string>
	 */
	public array $presets;

	/**
	 * Create a new component instance.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed       $value   The current shadow value.
	 * @param string|null $blockId The block ID for dispatching updates.
	 * @param string|null $label   Accessible label.
	 * @param string|null $id      Optional custom ID.
	 */
	public function __construct(
		public mixed $value = null,
		public ?string $blockId = null,
		public ?string $label = null,
		public ?string $id = null,
	) {
		$this->uuid    = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
		$this->label   = $label ?? __( 'visual-editor::ve.shadow' );
		$this->presets = $this->getDefaultPresets();
	}

	/**
	 * Get the default shadow presets.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, string>
	 */
	public function getDefaultPresets(): array
	{
		return [
			'none' => 'none',
			'sm'   => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
			'md'   => '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
			'lg'   => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
			'xl'   => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
			'2xl'  => '0 25px 50px -12px rgb(0 0 0 / 0.25)',
		];
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
		return view( 'visual-editor::components.shadow-control' );
	}
}
