<?php

/**
 * Link Control Component.
 *
 * A URL input with expandable panel for link text, open-in-new-tab, and nofollow toggles.
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
use Illuminate\View\Component;

/**
 * Link Control component for URL input with additional link options.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class LinkControl extends Component
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
	 * @param string|null $id       Optional custom ID.
	 * @param string|null $label    Label text.
	 * @param string|null $url      Current URL value.
	 * @param string|null $text     Link text.
	 * @param bool        $newTab   Whether to open in new tab.
	 * @param bool        $nofollow Whether to add nofollow.
	 * @param bool        $expanded Whether options panel is expanded.
	 * @param string|null $hint     Hint text.
	 * @param string|null $hintClass CSS class for hint.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public ?string $url = null,
		public ?string $text = null,
		public bool $newTab = false,
		public bool $nofollow = false,
		public bool $expanded = false,
		public ?string $hint = null,
		public ?string $hintClass = 'fieldset-label',
	) {
		$this->uuid = 've-' . md5( serialize( $this ) ) . $id;
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
		return view( 'visual-editor::components.link-control' );
	}
}
