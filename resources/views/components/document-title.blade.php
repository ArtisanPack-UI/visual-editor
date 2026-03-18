{{--
 * Document Title Component
 *
 * Text input bound to the editor store's meta bag for the document title.
 * Optionally auto-generates a slug on change.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$labelText       = $label ?? __( 'visual-editor::ve.document_title' );
	$placeholderText = $placeholder ?? __( 'visual-editor::ve.document_title_placeholder' );
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		value: Alpine.store( 'editor' )?.getMeta( {{ Js::from( $metaKey ) }}, '' ) ?? '',
		update( val ) {
			this.value = val;
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $metaKey ) }}, val );
				@if ( $autoSlug )
					Alpine.store( 'editor' ).setMeta(
						{{ Js::from( $slugKey ) }},
						val.toLowerCase()
							.replace( /[^a-z0-9\s-]/g, '' )
							.replace( /\s+/g, '-' )
							.replace( /-+/g, '-' )
							.replace( /^-|-$/g, '' )
					);
				@endif
			}
		},
	}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<label class="text-xs font-medium text-base-content/60">
		{{ $labelText }}
	</label>
	<input
		type="text"
		class="input input-sm w-full"
		x-model="value"
		x-on:input="update( $event.target.value )"
		placeholder="{{ $placeholderText }}"
	/>
</div>
