<?php

/**
 * Drop Zone Component.
 *
 * Handles drag-and-drop of blocks and files with visual feedback
 * including insertion line indicators and validation.
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
 * Drop Zone component for drag-and-drop interactions.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DropZone extends Component
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
	 * @param string|null $id               Optional custom ID.
	 * @param string|null $label            Accessible label for the drop zone.
	 * @param array       $acceptTypes      Accepted MIME types for file drops.
	 * @param bool        $allowFiles       Whether to accept file drops.
	 * @param bool        $allowBlocks      Whether to accept block drops.
	 * @param bool        $allowHtml        Whether to accept HTML drops.
	 * @param int|null    $maxFileSize      Maximum file size in KB.
	 * @param string|null $emptyMessage     Message shown when zone is empty.
	 * @param bool        $showInsertionLine Whether to show insertion position indicator.
	 * @param bool        $disabled         Whether dropping is disabled.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public array $acceptTypes = [],
		public bool $allowFiles = true,
		public bool $allowBlocks = true,
		public bool $allowHtml = true,
		public ?int $maxFileSize = null,
		public ?string $emptyMessage = null,
		public bool $showInsertionLine = true,
		public bool $disabled = false,
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
		return view( 'visual-editor::components.drop-zone' );
	}
}
