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
	public bool $multiSelect = true;

	/**
	 * Maximum number of selections allowed (0 = unlimited).
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public int $maxSelections = 0;

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

		$contextJson = json_encode( $context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR );
		$this->js( "window.__veMediaPickerContext = {$contextJson}" );

		$this->dispatch( 'open-media-modal', context: '' );
	}
}; ?>

<div>
	<livewire:media::media-modal
		:multi-select="$multiSelect"
		:max-selections="$maxSelections"
		context=""
	/>
</div>

<style>
	.media-library-modal .modal-box {
		max-width: 75rem;
	}
</style>

<script>
	function __veRegisterMediaListener() {
		Livewire.on( 'media-selected', ( data ) => {
			// Ignore toggle events from MediaItem (payload is { mediaId, selected }).
			if ( ! data || ! data.media || ! Array.isArray( data.media ) || ! data.media.length ) {
				return;
			}

			const ctx = window.__veMediaPickerContext || '';
			window.__veMediaPickerContext = '';
			window.dispatchEvent( new CustomEvent( 've-media-selected', {
				detail: { media: data.media, context: ctx }
			} ) );
		} );
	}

	if ( typeof Livewire !== 'undefined' ) {
		__veRegisterMediaListener();
	} else {
		document.addEventListener( 'livewire:init', __veRegisterMediaListener );
	}
</script>
