{{--
 * Document Taxonomies Component
 *
 * Multi-select / tag input bound to the editor store's meta bag
 * for taxonomy assignments. Accepts taxonomy options as a prop.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$labelText = $label ?? __( 'visual-editor::ve.document_taxonomies' );

	// Storage key is a flat string (e.g. "taxonomies.category") used as a single
	// key in the editor store's getMeta/setMeta — not nested object access.
	$storageKey = $taxonomy ? $metaKey . '.' . $taxonomy : $metaKey;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		selected: ( ( v ) => Array.isArray( v ) ? v : [] )( Alpine.store( 'editor' )?.getMeta( {{ Js::from( $storageKey ) }}, [] ) ?? [] ),
		options: {{ Js::from( $options ) }},

		toggle( value ) {
			const strValue = String( value );
			const index    = this.selected.indexOf( strValue );
			if ( -1 === index ) {
				this.selected.push( strValue );
			} else {
				this.selected.splice( index, 1 );
			}
			this.sync();
		},

		isSelected( value ) {
			return this.selected.includes( String( value ) );
		},

		sync() {
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).setMeta( {{ Js::from( $storageKey ) }}, [ ...this.selected ] );
			}
		},
	}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<label class="text-xs font-medium text-base-content/60">
		{{ $labelText }}
	</label>

	<div class="mt-1 max-h-40 overflow-y-auto space-y-1 border border-base-300 rounded-btn p-2" role="group" aria-label="{{ $labelText }}">
		<template x-for="( optionLabel, optionValue ) in options" :key="optionValue">
			<label class="flex items-center gap-2 cursor-pointer hover:bg-base-200 rounded px-1 py-0.5">
				<input
					type="checkbox"
					class="checkbox checkbox-xs"
					x-bind:value="optionValue"
					x-bind:checked="isSelected( optionValue )"
					x-on:change="toggle( optionValue )"
				/>
				<span class="text-sm" x-text="optionLabel"></span>
			</label>
		</template>

		<template x-if="Object.keys( options ).length === 0">
			<p class="text-xs text-base-content/40 py-1">
				{{ __( 'visual-editor::ve.document_taxonomies_empty' ) }}
			</p>
		</template>
	</div>
</div>
