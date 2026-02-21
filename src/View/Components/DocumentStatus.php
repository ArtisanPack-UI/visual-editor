<?php

/**
 * Document Status Component.
 *
 * Status selector for the Document tab with a conditional
 * date/time picker when "Scheduled" is selected.
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
 * Document Status component with status select and optional date picker.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentStatus extends Component
{
	/**
	 * Valid document statuses.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const STATUSES = [
		'draft',
		'published',
		'scheduled',
		'pending',
	];

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
	 * @param string|null $id            Optional custom ID.
	 * @param string      $status        Initial status: draft, published, scheduled, or pending.
	 * @param string|null $scheduledDate Date/time string for scheduled publishing.
	 */
	public function __construct(
		public ?string $id = null,
		public string $status = 'draft',
		public ?string $scheduledDate = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );

		if ( ! in_array( $this->status, self::STATUSES, true ) ) {
			$this->status = 'draft';
		}
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
		return view( 'visual-editor::components.document-status' );
	}
}
