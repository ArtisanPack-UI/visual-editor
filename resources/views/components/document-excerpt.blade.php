{{--
 * Document Excerpt Component
 *
 * Textarea bound to the editor store's meta bag for the document excerpt.
 * Supports optional character count and max length.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$labelText       = $label ?? __( 'visual-editor::ve.document_excerpt' );
	$placeholderText = $placeholder ?? __( 'visual-editor::ve.document_excerpt_placeholder' );
	$inputId         = $uuid . '-input';
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		value: Alpine.store( 'editor' )?.getMeta( {{ Js::from( $metaKey ) }}, '' ) ?? '',
		sync() {
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $metaKey ) }}, this.value );
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
	<label for="{{ $inputId }}" class="text-xs font-medium text-base-content/60">
		{{ $labelText }}
	</label>
	<textarea
		id="{{ $inputId }}"
		class="textarea textarea-sm w-full"
		rows="3"
		x-model="value"
		x-on:input="sync()"
		placeholder="{{ $placeholderText }}"
		@if ( $maxLength ) maxlength="{{ $maxLength }}" @endif
	></textarea>

	@if ( $maxLength )
		<p class="text-xs text-base-content/40 mt-1">
			<span x-text="value.length"></span> / {{ $maxLength }}
			{{ __( 'visual-editor::ve.characters' ) }}
		</p>
	@endif
</div>
