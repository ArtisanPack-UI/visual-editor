<?php

declare( strict_types=1 );

/**
 * Visual Editor - Status Bar
 *
 * Bottom status bar showing editor state indicators such as
 * save status, word count, and content status.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use Livewire\Component;

new class extends Component {
	/**
	 * The current save status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $saveStatus = 'saved';

	/**
	 * The word count of the content.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $wordCount = 0;

	/**
	 * The last saved timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $lastSaved = '';

	/**
	 * The content publish status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $contentStatus = 'draft';
}; ?>

<div class="ve-status-bar flex items-center justify-between border-t border-gray-200 bg-white px-4 py-1.5">
	{{-- Left: Save Status --}}
	<div class="flex items-center gap-3 text-xs text-gray-500">
		<span class="flex items-center gap-1">
			@if ( 'saving' === $saveStatus )
				<span class="h-2 w-2 animate-pulse rounded-full bg-yellow-400"></span>
				{{ __( 'Saving...' ) }}
			@elseif ( 'saved' === $saveStatus )
				<span class="h-2 w-2 rounded-full bg-green-400"></span>
				{{ __( 'Saved' ) }}
			@else
				<span class="h-2 w-2 rounded-full bg-orange-400"></span>
				{{ __( 'Unsaved changes' ) }}
			@endif
		</span>

		@if ( '' !== $lastSaved )
			<span class="text-gray-400">|</span>
			<span>{{ __( 'Last saved: :time', [ 'time' => $lastSaved ] ) }}</span>
		@endif
	</div>

	{{-- Right: Content Info --}}
	<div class="flex items-center gap-3 text-xs text-gray-500">
		<span>{{ trans_choice( ':count word|:count words', $wordCount, [ 'count' => $wordCount ] ) }}</span>
		<span class="text-gray-400">|</span>
		<span>{{ ucfirst( $contentStatus ) }}</span>
	</div>
</div>
