<?php

/**
 * Stub MediaModal Component for Testing.
 *
 * Provides a minimal stand-in for the media-library's MediaModal component
 * so that the visual-editor's media-picker tests can run without requiring
 * the full media-library view stack (icons, assets, etc.).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Stubs
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace Tests\Stubs;

use Livewire\Component;

/**
 * Stub replacement for ArtisanPackUI\MediaLibrary\Livewire\Components\MediaModal.
 *
 * @since 1.0.0
 */
class MediaModal extends Component
{
	/**
	 * Whether multiple items can be selected.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $multiSelect = false;

	/**
	 * Maximum number of selections allowed.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $maxSelections = 1;

	/**
	 * The context identifier.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $context = '';

	/**
	 * Render the stub component.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render(): string
	{
		return '<div></div>';
	}
}
