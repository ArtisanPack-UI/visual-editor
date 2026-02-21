<?php

/**
 * Status Bar Component.
 *
 * Bottom bar displaying block count, word count, save status,
 * and last saved time for the editor.
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
 * Status Bar component showing editor status information.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class StatusBar extends Component
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
	 * @param bool        $showBlockCount Whether to display block count.
	 * @param bool        $showWordCount  Whether to display word count.
	 * @param bool        $showSaveStatus Whether to display save status indicator.
	 * @param bool        $showLastSaved  Whether to display last saved timestamp.
	 */
	public function __construct(
		public ?string $id = null,
		public bool $showBlockCount = true,
		public bool $showWordCount = true,
		public bool $showSaveStatus = true,
		public bool $showLastSaved = true,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
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
		return view( 'visual-editor::components.status-bar' );
	}
}
