<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
	/**
	 * The context identifier for this media picker instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $context = '';

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
	 * Open the media picker with a given context.
	 *
	 * Stores the context in JavaScript before dispatching the
	 * Livewire event so the MediaModal (mounted with empty
	 * context) will accept the open request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context The context identifier.
	 *
	 * @return void
	 */
	#[On( 'open-ve-media-picker' )]
	public function open( string $context = 'visual-editor' ): void
	{
		$this->context = $context;

		$contextJson = json_encode( $context );
		$this->js( "window.__veMediaPickerContext = {$contextJson}" );

		$this->dispatch( 'open-media-modal', context: '' );
	}

	/**
	 * Handle media selection from the media library modal.
	 *
	 * Re-dispatches the selection as a browser CustomEvent so
	 * Alpine listeners (x-on:ve-media-selected.window) can
	 * receive the data with the original request context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $media   The selected media items.
	 * @param string                           $context The context of the selection.
	 *
	 * @return void
	 */
	#[On( 'media-selected' )]
	public function onMediaSelected( array $media, string $context = '' ): void
	{
		$mediaJson = json_encode( $media );

		$this->js( "
			const ctx = window.__veMediaPickerContext || '';
			window.__veMediaPickerContext = '';
			window.dispatchEvent( new CustomEvent( 've-media-selected', {
				detail: { media: {$mediaJson}, context: ctx }
			} ) );
		" );
	}
}; ?>

<div>
	<livewire:media::media-modal
		:multi-select="$multiSelect"
		:max-selections="$maxSelections"
		context=""
	/>
</div>
