{{--
 * Template Editor Page
 *
 * Full-page Livewire view that mounts the template block editor.
 * Listens for the top toolbar's ve-save-request event and wires the
 * Alpine editor store to the Livewire save method.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Livewire\SiteEditor
 *
 * @since      1.0.0
 --}}

<div
	x-data="{
		isSaving: false,
		save() {
			if ( this.isSaving ) return;
			const store = Alpine.store( 'editor' );
			if ( ! store || ! store.markSaving ) return;

			// Flush any active contenteditable so the focusout handler
			// syncs the latest text into the store before we serialize.
			const active = document.activeElement;
			if ( active && active.isContentEditable ) {
				active.blur();
			}

			// Wait a tick for the blur/focusout handler to update the store.
			setTimeout( () => {
				this.isSaving = true;
				store.markSaving();

				const blocks   = JSON.parse( JSON.stringify( store.blocks ) );
				const settings = JSON.parse( JSON.stringify( store.templateSettings || {} ) );

				$wire.call( 'save', blocks, settings ).then( () => {
					store.dirty = false;
					if ( store.markSaved ) store.markSaved();
				} ).catch( () => {
					if ( store.markDirty ) store.markDirty();
				} ).finally( () => {
					this.isSaving = false;
				} );
			}, 0 );
		},
	}"
	x-on:ve-save-request.window="save()"
	x-on:keydown.ctrl.s.prevent="save()"
	x-on:keydown.meta.s.prevent="save()"
>
	{{-- Template Editor — wire:ignore prevents Livewire morphdom
	     from interfering with contenteditable focus in the block editor --}}
	<div wire:ignore>
		<x-ve-template-editor
			:initial-blocks="$initialBlocks"
			:template-settings="$templateSettings"
			:autosave="false"
			:show-sidebar="true"
		/>
	</div>
</div>
