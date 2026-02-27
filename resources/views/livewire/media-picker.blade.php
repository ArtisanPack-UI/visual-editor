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
	public string $context = 'visual-editor';

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
	 * Whether the media modal is currently open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isOpen = false;

	/**
	 * Open the media picker with a given context.
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
		$this->isOpen  = true;

		$this->dispatch( 'open-media-modal', context: $this->context );
	}

	/**
	 * Handle media selection from the media library modal.
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
		$this->isOpen = false;

		$this->dispatch( 've-media-selected', media: $media, context: $context );
	}
}; ?>

<div>
	@if ( $isOpen )
		<livewire:media::media-modal
			:multi-select="$multiSelect"
			:max-selections="$maxSelections"
			:context="$context"
		/>
	@endif
</div>
