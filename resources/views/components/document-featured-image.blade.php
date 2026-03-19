{{--
 * Document Featured Image Component
 *
 * Image picker bound to the editor store's meta bag for the featured image.
 * Stores the media ID in the meta bag (meta key) and keeps the URL in a
 * companion key (meta key + "_url") for display. Integrates with the
 * artisanpack-ui/media-library MediaModal via the ve-media-picker bridge.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$labelText    = $label ?? __( 'visual-editor::ve.document_featured_image' );
	$mediaContext = 'featured-image-' . $uuid;
	$urlKey       = $metaKey . '_url';
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		mediaId: Alpine.store( 'editor' )?.getMeta( {{ Js::from( $metaKey ) }}, null ) ?? null,
		imageUrl: Alpine.store( 'editor' )?.getMeta( {{ Js::from( $urlKey ) }}, '' ) ?? '',
		mediaContext: {{ Js::from( $mediaContext ) }},
		_objectUrl: null,

		_revokeObjectUrl() {
			if ( this._objectUrl ) {
				URL.revokeObjectURL( this._objectUrl );
				this._objectUrl = null;
			}
		},

		setImage( id, url ) {
			this._revokeObjectUrl();
			this.mediaId  = id;
			this.imageUrl = url;
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $metaKey ) }}, id );
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $urlKey ) }}, url );
			}
		},

		removeImage() {
			this._revokeObjectUrl();
			this.mediaId  = null;
			this.imageUrl = '';
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $metaKey ) }}, null );
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $urlKey ) }}, '' );
			}
		},

		@if ( $hasMediaLibrary )
			openMediaPicker() {
				Livewire.dispatch( 'open-ve-media-picker', { context: this.mediaContext } );
			},
		@endif

		handleFileSelect( event ) {
			const file = event.target.files[ 0 ];
			if ( file ) {
				const objectUrl = URL.createObjectURL( file );
				this.setImage( null, objectUrl );
				this._objectUrl = objectUrl;
			}
		},
	}"
	@if ( $hasMediaLibrary )
		x-on:ve-media-selected.window="
			if ( $event.detail?.context === mediaContext && $event.detail?.media?.length ) {
				const m = $event.detail.media[ 0 ];
				setImage( m.id ?? null, m.url ?? m.path ?? '' );
			}
		"
	@endif
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<label class="text-xs font-medium text-base-content/60">
		{{ $labelText }}
	</label>

	{{-- Thumbnail preview --}}
	<div x-show="imageUrl" x-transition class="mt-2 relative group">
		<img
			x-bind:src="imageUrl"
			alt="{{ __( 'visual-editor::ve.featured_image_preview' ) }}"
			class="w-full rounded-btn border border-base-300 object-cover max-h-48"
		/>
		<button
			type="button"
			class="btn btn-xs btn-circle btn-error absolute top-2 right-2 opacity-0 group-hover:opacity-100 focus:opacity-100 focus-visible:opacity-100 transition-opacity"
			x-on:click="removeImage()"
			aria-label="{{ __( 'visual-editor::ve.featured_image_remove' ) }}"
		>
			<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
				<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
			</svg>
		</button>
	</div>

	{{-- Upload / Select controls --}}
	<div x-show="!imageUrl" x-transition class="mt-2 space-y-2">
		@if ( $hasMediaLibrary )
			<button
				type="button"
				class="btn btn-sm btn-outline w-full"
				x-on:click="openMediaPicker()"
			>
				{{ __( 'visual-editor::ve.featured_image_select' ) }}
			</button>
		@else
			<input
				type="file"
				class="file-input file-input-sm w-full"
				accept="image/*"
				x-on:change="handleFileSelect( $event )"
			/>
		@endif
	</div>
</div>
