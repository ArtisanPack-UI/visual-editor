<?php

/**
 * Slash Command Inserter Component.
 *
 * An inline dropdown that appears when the user types "/" in an empty
 * paragraph block, allowing them to quickly insert any block type.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Slash Command Inserter component for quick block insertion.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.1.0
 */
class SlashCommandInserter extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.1.0
	 *
	 * @param string|null  $id     Optional custom ID.
	 * @param array<mixed> $blocks Available blocks for insertion.
	 */
	public function __construct(
		public ?string $id = null,
		public array $blocks = [],
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.1.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.slash-command-inserter' );
	}
}
