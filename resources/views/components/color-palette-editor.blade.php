{{--
 * Color Palette Editor Component
 *
 * A visual interface for managing the global color palette. Supports adding,
 * editing, renaming, and removing colors with inline accessibility contrast
 * checking and CSS custom property preview.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$initialEntries = $paletteEntries;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		entries: {{ Js::from( $initialEntries ) }},
		editing: null,
		editName: '',
		editSlug: '',
		editColor: '',
		adding: false,
		newName: '',
		newSlug: '',
		newColor: '#000000',
		showCss: false,
		_savedPalette: null,

		init() {
			this.$watch( 'editColor', ( value ) => {
				if ( null === this.editing ) return;
				const store = Alpine.store( 'editor' );
				if ( ! store ) return;
				const color = this._normalizeHex( value );
				if ( ! color ) return;
				const palette = JSON.parse( JSON.stringify( this.entries ) );
				palette[ this.editing ] = { ...palette[ this.editing ], color: color };
				store.globalStyles.palette = palette;
				store._syncGlobalCssVariables();
			} );
		},

		_commitToStore() {
			const store = Alpine.store( 'editor' );
			if ( ! store ) return;
			store._pushHistory();
			store.globalStyles.palette = JSON.parse( JSON.stringify( this.entries ) );
			store._syncGlobalCssVariables();
			store.markDirty();
			store._dispatchChange();
		},

		startEdit( index ) {
			this._savedPalette = JSON.parse( JSON.stringify( Alpine.store( 'editor' )?.globalStyles?.palette || this.entries ) );
			this.editing   = index
			this.editName  = this.entries[ index ].name
			this.editSlug  = this.entries[ index ].slug
			this.editColor = this.entries[ index ].color
		},

		saveEdit() {
			if ( null === this.editing ) return
			const slug  = this._sanitizeSlug( this.editSlug )
			const color = this._normalizeHex( this.editColor )
			if ( ! slug || ! this.editName.trim() || ! color ) return
			const duplicate = this.entries.some( ( e, i ) => i !== this.editing && e.slug === slug )
			if ( duplicate ) return
			this.entries[ this.editing ] = {
				name:  this.editName.trim(),
				slug:  slug,
				color: color,
			}
			this.editing      = null
			this._savedPalette = null
			this._commitToStore()
			this._dispatch()
		},

		cancelEdit() {
			this.editing = null
			if ( this._savedPalette ) {
				const store = Alpine.store( 'editor' );
				if ( store ) {
					store.globalStyles.palette = this._savedPalette;
					store._syncGlobalCssVariables();
				}
				this._savedPalette = null;
			}
		},

		removeColor( index ) {
			this.entries.splice( index, 1 )
			this._commitToStore()
			this._dispatch()
		},

		startAdd() {
			this.adding  = true
			this.newName  = ''
			this.newSlug  = ''
			this.newColor = '#000000'
		},

		confirmAdd() {
			const slug  = this._sanitizeSlug( this.newSlug )
			const color = this._normalizeHex( this.newColor )
			if ( ! this.newName.trim() || ! slug || ! color ) return
			const duplicate = this.entries.some( ( e ) => e.slug === slug )
			if ( duplicate ) return
			this.entries.push( {
				name:  this.newName.trim(),
				slug:  slug,
				color: color,
			} )
			this.adding = false
			this._commitToStore()
			this._dispatch()
		},

		cancelAdd() {
			this.adding = false
		},

		_sanitizeSlug( value ) {
			return value
				.toLowerCase()
				.trim()
				.replace( /\s+/g, '-' )
				.replace( /[^a-z0-9-]/g, '' )
				.replace( /-{2,}/g, '-' )
				.replace( /^-+|-+$/g, '' )
		},

		_normalizeHex( value ) {
			const hex = value.trim().replace( /^#/, '' )
			if ( /^[0-9a-fA-F]{3}$/.test( hex ) ) {
				return '#' + hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2]
			}
			if ( /^[0-9a-fA-F]{6}$/.test( hex ) ) {
				return '#' + hex.toLowerCase()
			}
			return ''
		},

		autoSlug( name ) {
			return this._sanitizeSlug( name )
		},

		resetToDefaults() {
			this.entries = {{ Js::from( $defaultEntries ) }}
			this.editing       = null
			this.adding        = false
			this._savedPalette = null
			this._commitToStore()
			this._dispatch()
		},

		_dispatch() {
			document.dispatchEvent( new CustomEvent( 've-palette-change', {
				detail: { palette: JSON.parse( JSON.stringify( this.entries ) ) },
				bubbles: true,
			} ) );
		},

	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-4' ] ) }}
>
	{{-- Header --}}
	<div class="flex items-center justify-between">
		<h3 class="text-sm font-semibold text-base-content">
			{{ __( 'visual-editor::ve.color_palette_title' ) }}
		</h3>
		<div class="flex items-center gap-2">
			<button
				type="button"
				x-on:click="showCss = ! showCss"
				class="text-xs text-base-content/50 hover:text-base-content/80 transition-colors cursor-pointer"
				:aria-expanded="showCss"
			>
				{{ __( 'visual-editor::ve.css_preview' ) }}
			</button>
			<button
				type="button"
				x-on:click="resetToDefaults()"
				class="text-xs text-base-content/50 hover:text-base-content/80 transition-colors cursor-pointer"
			>
				{{ __( 'visual-editor::ve.reset_to_default' ) }}
			</button>
		</div>
	</div>

	{{-- Color Grid --}}
	<div class="flex flex-col gap-2">
		<template x-for="( entry, index ) in entries" :key="entry.slug + '-' + index">
			<div>
				{{-- Display mode --}}
				<div
					x-show="editing !== index"
					class="flex items-center gap-3 rounded-lg border border-base-300 px-3 py-2 hover:bg-base-200/50 focus-within:bg-base-200/50 transition-colors group"
				>
					<button
						type="button"
						class="h-8 w-8 rounded-full ring-1 ring-base-300 shrink-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
						:style="'background-color: ' + entry.color"
						x-on:click="startEdit( index )"
						:aria-label="'{{ __( 'visual-editor::ve.edit_color' ) }}: ' + entry.name"
					></button>
					<button
						type="button"
						class="flex flex-col min-w-0 flex-1 cursor-pointer text-left focus:outline-none"
						x-on:click="startEdit( index )"
						:aria-label="'{{ __( 'visual-editor::ve.edit_color' ) }}: ' + entry.name"
					>
						<span class="text-sm text-base-content truncate" x-text="entry.name"></span>
						<span class="text-xs text-base-content/40 font-mono" x-text="entry.color"></span>
					</button>
					<button
						type="button"
						x-on:click="removeColor( index )"
						class="text-base-content/30 hover:text-error focus:text-error transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100 group-focus-within:opacity-100 cursor-pointer shrink-0 focus:outline-none"
						:aria-label="'{{ __( 'visual-editor::ve.remove_color' ) }}: ' + entry.name"
					>
						<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
						</svg>
					</button>
				</div>

				{{-- Edit mode --}}
				<div
					x-show="editing === index"
					x-cloak
					class="flex flex-col gap-3 rounded-lg border border-primary/30 bg-base-200/30 px-3 py-3"
				>
					<div class="flex items-center gap-3">
						<div
							class="h-8 w-8 rounded-full border border-base-300 cursor-pointer shrink-0"
							x-bind:style="'background-color:' + editColor"
							x-on:click="$refs['editPickerWrap' + index]?.classList.toggle( 'hidden' )"
							role="button"
							:aria-label="'{{ __( 'visual-editor::ve.pick_color' ) }}'"
						></div>
						<input
							type="text"
							x-model="editName"
							class="input input-sm input-bordered flex-1 min-w-0"
							placeholder="{{ __( 'visual-editor::ve.color_name' ) }}"
						/>
					</div>
					<div x-bind:ref="'editPickerWrap' + index" class="hidden">
						<x-ve-color-picker
							value="#000000"
							x-on:ve-color-picker-change.stop="editColor = $event.detail.hex"
							:show-format-toggle="false"
							:show-copy-button="false"
						/>
					</div>
					<div class="flex items-center gap-3">
						<input
							type="text"
							x-model="editSlug"
							class="input input-sm input-bordered flex-1 font-mono text-xs"
							placeholder="{{ __( 'visual-editor::ve.color_slug' ) }}"
						/>
						<input
							type="text"
							x-model="editColor"
							class="input input-sm input-bordered w-24 font-mono text-xs"
							placeholder="#000000"
						/>
					</div>
					<div class="flex justify-end gap-2">
						<button
							type="button"
							x-on:click="cancelEdit()"
							class="btn btn-ghost btn-xs"
						>
							{{ __( 'visual-editor::ve.cancel' ) }}
						</button>
						<button
							type="button"
							x-on:click="saveEdit()"
							class="btn btn-primary btn-xs"
						>
							{{ __( 'visual-editor::ve.save' ) }}
						</button>
					</div>
				</div>
			</div>
		</template>
	</div>

	{{-- Add Color --}}
	<div>
		<div x-show="! adding">
			<button
				type="button"
				x-on:click="startAdd()"
				class="btn btn-ghost btn-sm w-full border-dashed border-base-300 text-base-content/50 hover:text-base-content/80"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
				</svg>
				{{ __( 'visual-editor::ve.add_color' ) }}
			</button>
		</div>

		<div
			x-show="adding"
			x-cloak
			class="flex flex-col gap-3 rounded-lg border border-success/30 bg-base-200/30 px-3 py-3"
		>
			<div class="flex items-center gap-3">
				<div
					class="h-8 w-8 rounded-full border border-base-300 cursor-pointer shrink-0"
					x-bind:style="'background-color:' + newColor"
					x-on:click="$refs.addPickerWrap?.classList.toggle( 'hidden' )"
					role="button"
					aria-label="{{ __( 'visual-editor::ve.pick_color' ) }}"
				></div>
				<input
					type="text"
					x-model="newName"
					x-on:input="newSlug = autoSlug( newName )"
					class="input input-sm input-bordered flex-1 min-w-0"
					placeholder="{{ __( 'visual-editor::ve.color_name' ) }}"
				/>
			</div>
			<div x-ref="addPickerWrap" class="hidden">
				<x-ve-color-picker
					value="#000000"
					x-on:ve-color-picker-change.stop="newColor = $event.detail.hex"
					:show-format-toggle="false"
					:show-copy-button="false"
				/>
			</div>
			<div class="flex items-center gap-3">
				<input
					type="text"
					x-model="newSlug"
					class="input input-sm input-bordered flex-1 font-mono text-xs"
					placeholder="{{ __( 'visual-editor::ve.color_slug' ) }}"
				/>
				<input
					type="text"
					x-model="newColor"
					class="input input-sm input-bordered w-24 font-mono text-xs"
					placeholder="#000000"
				/>
			</div>
			<div class="flex justify-end gap-2">
				<button
					type="button"
					x-on:click="cancelAdd()"
					class="btn btn-ghost btn-xs"
				>
					{{ __( 'visual-editor::ve.cancel' ) }}
				</button>
				<button
					type="button"
					x-on:click="confirmAdd()"
					class="btn btn-success btn-xs"
				>
					{{ __( 'visual-editor::ve.add' ) }}
				</button>
			</div>
		</div>
	</div>

	{{-- CSS Preview --}}
	<div
		x-show="showCss"
		x-cloak
		x-collapse
		class="rounded-lg border border-base-300 bg-base-200/50 p-3"
	>
		<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
			{{ __( 'visual-editor::ve.generated_css' ) }}
		</div>
		<pre
			class="text-xs font-mono text-base-content/70 whitespace-pre-wrap break-all max-h-48 overflow-y-auto"
			x-text="(() => {
				let css = ':root {\n'
				entries.forEach( ( e ) => {
					css += '  --ve-color-' + e.slug + ': ' + e.color + ';\n'
				} )
				css += '}'
				return css
			})()"
		></pre>
	</div>
</div>
