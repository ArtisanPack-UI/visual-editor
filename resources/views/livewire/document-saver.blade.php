<?php

use ArtisanPackUI\VisualEditor\Livewire\Forms\DocumentForm;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
	/**
	 * The document form object.
	 *
	 * @since 1.0.0
	 *
	 * @var DocumentForm
	 */
	public DocumentForm $form;

	/**
	 * The document ID being edited.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	public ?int $documentId = null;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $documentId The document ID to load.
	 *
	 * @return void
	 */
	public function mount( ?int $documentId = null ): void
	{
		$this->documentId      = $documentId;
		$this->form->documentId = $documentId;
	}

	/**
	 * Save the document.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks         The block content.
	 * @param string                           $documentStatus The document status.
	 * @param string|null                      $scheduledDate  The scheduled date.
	 * @param array<string, mixed>             $meta           Meta key-value pairs from document panel fields.
	 *
	 * @return void
	 */
	public function save( array $blocks, string $documentStatus = 'draft', ?string $scheduledDate = null, array $meta = [] ): void
	{
		$this->form->blocks         = $blocks;
		$this->form->documentStatus = $documentStatus;
		$this->form->scheduledDate  = $scheduledDate;
		$this->form->meta           = $meta;

		$this->form->validate();

		if ( function_exists( 'doAction' ) ) {
			doAction( 'ap.visualEditor.document.saving', $this->documentId, $blocks, $documentStatus, $meta );
		}

		$this->dispatch( 've-document-saved', documentId: $this->documentId );

		if ( function_exists( 'doAction' ) ) {
			doAction( 'ap.visualEditor.document.saved', $this->documentId, $blocks, $documentStatus, $meta );
		}
	}

	/**
	 * Handle autosave events from the Alpine editor store.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks         The block content.
	 * @param string                           $documentStatus The document status.
	 * @param string|null                      $scheduledDate  The scheduled date.
	 * @param array<string, mixed>             $meta           Meta key-value pairs from document panel fields.
	 *
	 * @return void
	 */
	#[On( 've-autosave' )]
	public function autosave( array $blocks, string $documentStatus = 'draft', ?string $scheduledDate = null, array $meta = [] ): void
	{
		try {
			$this->save( $blocks, $documentStatus, $scheduledDate, $meta );
		} catch ( \Throwable $e ) {
			$this->dispatch( 've-document-error', message: $e->getMessage() );
		}
	}
}; ?>

<div>
	{{-- Document saver is a headless component — no visible UI --}}
</div>
