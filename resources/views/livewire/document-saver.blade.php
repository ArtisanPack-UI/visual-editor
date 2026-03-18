<?php

use ArtisanPackUI\VisualEditor\Contracts\EditorContent;
use ArtisanPackUI\VisualEditor\Livewire\Forms\DocumentForm;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
	/**
	 * The Eloquent model implementing EditorContent.
	 *
	 * @since 1.0.0
	 *
	 * @var EditorContent
	 */
	public EditorContent $model;

	/**
	 * The document form object.
	 *
	 * @since 1.0.0
	 *
	 * @var DocumentForm
	 */
	public DocumentForm $form;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param EditorContent $model The model implementing EditorContent.
	 *
	 * @return void
	 */
	public function mount( EditorContent $model ): void
	{
		$this->model            = $model;
		$this->form->documentId = $model->getKey();
	}

	/**
	 * Save the document.
	 *
	 * Validates the payload via DocumentForm, authorizes the current user
	 * via Laravel policy (when a policy exists), then delegates persistence
	 * to the model's saveFromEditor method.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $payload The editor payload containing blocks, documentStatus, scheduledDate, and meta.
	 *
	 * @return void
	 */
	public function save( array $payload ): void
	{
		$this->form->blocks         = $payload['blocks'] ?? [];
		$this->form->documentStatus = $payload['documentStatus'] ?? 'draft';
		$this->form->scheduledDate  = $payload['scheduledDate'] ?? null;
		$this->form->meta           = $payload['meta'] ?? [];

		$this->form->validate();

		if ( Gate::getPolicyFor( $this->model ) ) {
			Gate::authorize( 'update', $this->model );
		}

		if ( function_exists( 'doAction' ) ) {
			doAction( 'ap.visualEditor.document.saving', $this->model, $payload );
		}

		$meta = array_merge( $this->form->meta, [
			'blocks'        => $this->form->blocks,
			'status'        => $this->form->documentStatus,
			'scheduledDate' => $this->form->scheduledDate,
		] );

		$this->model->saveFromEditor( $meta );

		$this->dispatch( 've-document-saved', documentId: $this->model->getKey(), scheduledDate: $this->form->scheduledDate );

		if ( function_exists( 'doAction' ) ) {
			doAction( 'ap.visualEditor.document.saved', $this->model, $payload );
		}
	}

	/**
	 * Handle autosave events from the Alpine editor store.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $payload The editor payload.
	 *
	 * @return void
	 */
	public function autosave( array $payload ): void
	{
		try {
			$this->save( $payload );
		} catch ( \Throwable $e ) {
			$this->dispatch( 've-document-error', message: $e->getMessage() );
		}
	}
}; ?>

<div
	x-on:ve-save-request.window="
		$store.editor.markSaving();
		$wire.save( {
			blocks: JSON.parse( JSON.stringify( $store.editor.blocks ) ),
			documentStatus: $store.editor.documentStatus,
			scheduledDate: $store.editor.scheduledDate,
			meta: { ...$store.editor.meta }
		} )
	"
	x-on:ve-autosave.window="
		$store.editor.markSaving();
		$wire.autosave( $event.detail )
	"
>
	{{-- Document saver is a headless bridge component — no visible UI --}}
</div>
