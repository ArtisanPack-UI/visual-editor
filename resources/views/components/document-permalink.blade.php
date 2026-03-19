{{--
 * Document Permalink Component
 *
 * Slug input bound to the editor store's meta bag for the document permalink.
 * Shows a base URL prefix with an editable slug portion.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$labelText = $label ?? __( 'visual-editor::ve.document_permalink' );
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		value: Alpine.store( 'editor' )?.getMeta( {{ Js::from( $metaKey ) }}, '' ) ?? '',
		_permalinkDebounceTimer: null,
		update( val ) {
			const slug = val.toLowerCase()
				.replace( /[^a-z0-9\s-]/g, '' )
				.replace( /\s+/g, '-' )
				.replace( /-+/g, '-' )
				.replace( /^-|-$/g, '' );
			this.value = slug;
			if ( Alpine.store( 'editor' ) ) {
				clearTimeout( this._permalinkDebounceTimer );
				this._permalinkDebounceTimer = setTimeout( () => {
					Alpine.store( 'editor' ).setMeta( {{ Js::from( $metaKey ) }}, slug );
				}, 300 );
			}
		},
		init() {
			this.$watch( () => Alpine.store( 'editor' )?.getMeta( {{ Js::from( $metaKey ) }}, '' ), ( newVal ) => {
				if ( newVal !== this.value ) {
					this.value = newVal ?? '';
				}
			} );
		},
	}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<label class="text-xs font-medium text-base-content/60">
		{{ $labelText }}
	</label>
	<div class="flex items-center gap-0">
		<span class="text-xs text-base-content/40 shrink-0 bg-base-200 px-2 py-1 rounded-l-btn border border-r-0 border-base-300 h-8 flex items-center">
			{{ $baseUrl }}
		</span>
		<input
			type="text"
			class="input input-sm w-full rounded-l-none"
			x-model="value"
			x-on:input="update( $event.target.value )"
			placeholder="{{ __( 'visual-editor::ve.document_permalink_placeholder' ) }}"
		/>
	</div>
</div>
