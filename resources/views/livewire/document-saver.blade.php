<?php

use ArtisanPackUI\VisualEditor\Contracts\EditorContent;
use ArtisanPackUI\VisualEditor\Livewire\Forms\DocumentForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
	/**
	 * The Eloquent model implementing EditorContent.
	 *
	 * @since 1.0.0
	 *
	 * @var EditorContent&Model
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
	 * @param EditorContent&Model $model The Eloquent model implementing EditorContent.
	 *
	 * @return void
	 */
	public function mount( EditorContent&Model $model ): void
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
		try {
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

			if ( ! empty( $payload['isAutosave'] ) ) {
				$meta['isAutosave'] = true;
			}

			$this->model->saveFromEditor( $meta );

			$this->dispatch( 've-document-saved', documentId: $this->model->getKey(), scheduledDate: $this->form->scheduledDate );

			if ( function_exists( 'doAction' ) ) {
				doAction( 'ap.visualEditor.document.saved', $this->model, $payload );
			}
		} catch ( \Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			report( $e );
			$this->dispatch( 've-document-error', message: __( 'visual-editor::ve.document_save_failed' ) );
		}
	}

	/**
	 * Handle autosave events from the Alpine editor store.
	 *
	 * Marks the payload as an autosave so the trait can skip
	 * revision creation when autosave_revisions is disabled.
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
			$payload['isAutosave'] = true;

			$this->save( $payload );
		} catch ( \Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e ) {
			report( $e );
			$this->dispatch( 've-document-error', message: __( 'visual-editor::ve.document_save_failed' ) );
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
		} ).catch( ( err ) => {
			document.dispatchEvent( new CustomEvent( 've-document-error', { detail: { message: err.message }, bubbles: true } ) );
		} )
	"
	x-on:ve-autosave.window="
		$store.editor.markSaving();
		$wire.autosave( $event.detail ).catch( ( err ) => {
			document.dispatchEvent( new CustomEvent( 've-document-error', { detail: { message: err.message }, bubbles: true } ) );
		} )
	"
>
	{{-- Document saver is a headless bridge component — no visible UI --}}
</div>
