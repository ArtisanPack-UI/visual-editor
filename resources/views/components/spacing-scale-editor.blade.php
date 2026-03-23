{{--
 * Spacing Scale Editor Component
 *
 * A visual interface for managing the global spacing scale. Supports editing
 * scale step values, applying presets (compact, default, spacious), configuring
 * block gap, adding custom spacing steps, and previewing generated CSS.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$presets = [
		[ 'key' => 'compact', 'label' => __( 'visual-editor::ve.spacing_preset_compact' ) ],
		[ 'key' => 'default', 'label' => __( 'visual-editor::ve.spacing_preset_default' ) ],
		[ 'key' => 'spacious', 'label' => __( 'visual-editor::ve.spacing_preset_spacious' ) ],
	];

	$presetScales      = \ArtisanPackUI\VisualEditor\Services\SpacingScaleManager::PRESETS;
	$initialBaseValues = $baseValues;
	$hasOverrideMode   = null !== $baseValues;
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		scale: {{ Js::from( $spacingData['scale'] ) }},
		blockGap: {{ Js::from( $spacingData['blockGap'] ) }},
		customSteps: {{ Js::from( $spacingData['customSteps'] ) }},
		baseValues: {{ Js::from( $initialBaseValues ) }},
		overrideMode: {{ Js::from( $hasOverrideMode ) }},
		editing: null,
		editName: '',
		editSlug: '',
		editValue: '',
		editError: '',
		adding: false,
		newName: '',
		newSlug: '',
		newValue: '',
		slugError: '',
		showCss: false,
		presets: {{ Js::from( $presetScales ) }},

		_getStore() {
			return Alpine.store( 'editor' ) || Alpine.store( 'globalStyles' ) || null;
		},

		isStepOverridden( index, isCustom ) {
			if ( ! this.overrideMode || ! this.baseValues ) return false;
			const list = isCustom ? this.customSteps : this.scale;
			const step = list[ index ];
			if ( ! step ) return false;
			const baseList = isCustom ? ( this.baseValues.customSteps || [] ) : ( this.baseValues.scale || [] );
			const base = baseList.find( b => b.slug === step.slug );
			if ( ! base ) return true;
			return base.value !== step.value || base.name !== step.name;
		},

		resetStepToBase( index, isCustom ) {
			if ( ! this.baseValues ) return;
			const list = isCustom ? this.customSteps : this.scale;
			const step = list[ index ];
			if ( ! step ) return;
			const baseList = isCustom ? ( this.baseValues.customSteps || [] ) : ( this.baseValues.scale || [] );
			const base = baseList.find( b => b.slug === step.slug );
			if ( ! base ) return;
			list.splice( index, 1, { ...base } );
			this._dispatch();
		},

		init() {
			const syncToStore = () => {
				const store = this._getStore();
				if ( ! store ) return;
				store.globalStyles.spacing = {
					scale: JSON.parse( JSON.stringify( this.scale ) ),
					blockGap: this.blockGap,
					customSteps: JSON.parse( JSON.stringify( this.customSteps ) ),
				};
				store._syncGlobalCssVariables();
				store.markDirty();
				store._dispatchChange();
			};
			this.$watch( 'scale', syncToStore );
			this.$watch( 'blockGap', syncToStore );
			this.$watch( 'customSteps', syncToStore );
		},

		startEdit( index, isCustom ) {
			const list = isCustom ? this.customSteps : this.scale
			this.editing   = { index, isCustom }
			this.editName  = list[ index ].name
			this.editSlug  = list[ index ].slug
			this.editValue = list[ index ].value
			this._syncEditFromValue()
		},

		saveEdit() {
			if ( ! this.editing ) return
			this.editError = ''
			const value = this.editValue.trim()
			if ( ! this.editName.trim() || ! value ) {
				this.editError = '{{ __( 'visual-editor::ve.spacing_edit_required' ) }}'
				return
			}
			if ( ! this._isValidDimension( value ) ) {
				this.editError = '{{ __( 'visual-editor::ve.spacing_invalid_value' ) }}'
				return
			}
			const list = this.editing.isCustom ? this.customSteps : this.scale
			list[ this.editing.index ] = {
				name:  this.editName.trim(),
				slug:  list[ this.editing.index ].slug,
				value: value,
			}
			this.editing   = null
			this.editError = ''
		},

		cancelEdit() {
			this.editing   = null
			this.editError = ''
		},

		removeCustomStep( index ) {
			this.customSteps.splice( index, 1 )
		},

		startAdd() {
			this.adding    = true
			this.newName   = ''
			this.newSlug   = ''
			this.newValue  = ''
			this.newNum    = ''
			this.newUnit   = 'rem'
			this.slugError = ''
		},

		confirmAdd() {
			this.slugError = ''
			const slug  = this._sanitizeSlug( this.newSlug )
			const value = this.newValue.trim()
			if ( ! this.newName.trim() || ! slug || ! value ) return
			if ( ! this._isValidDimension( value ) ) {
				this.slugError = '{{ __( 'visual-editor::ve.spacing_invalid_value' ) }}'
				return
			}
			const allSlugs = [
				...this.scale.map( s => s.slug ),
				...this.customSteps.map( s => s.slug ),
			]
			if ( allSlugs.includes( slug ) ) {
				this.slugError = '{{ __( 'visual-editor::ve.spacing_slug_exists' ) }}'
				return
			}
			this.customSteps.push( {
				name:  this.newName.trim(),
				slug:  slug,
				value: value,
			} )
			this.adding    = false
			this.slugError = ''
		},

		cancelAdd() {
			this.adding  = false
			this.newNum  = ''
			this.newUnit = 'rem'
		},

		applyPreset( key ) {
			const preset = this.presets[ key ]
			if ( ! preset ) return
			const names = {
				xs: '{{ __( 'visual-editor::ve.spacing_xs' ) }}',
				sm: '{{ __( 'visual-editor::ve.spacing_sm' ) }}',
				md: '{{ __( 'visual-editor::ve.spacing_md' ) }}',
				lg: '{{ __( 'visual-editor::ve.spacing_lg' ) }}',
				xl: '{{ __( 'visual-editor::ve.spacing_xl' ) }}',
				'2xl': '{{ __( 'visual-editor::ve.spacing_2xl' ) }}',
				'3xl': '{{ __( 'visual-editor::ve.spacing_3xl' ) }}',
			}
			this.scale = Object.entries( preset.scale ).map( ( [ slug, value ] ) => ( {
				name: names[ slug ] || slug,
				slug,
				value,
			} ) )
			this.blockGap    = preset.blockGap
			this.customSteps = []
			this.editing     = null
			this.adding      = false
		},

		setBlockGap( slug ) {
			this.blockGap = slug
		},

		resetToDefaults() {
			this.scale       = {{ Js::from( $defaultData['scale'] ) }}
			this.blockGap    = {{ Js::from( $defaultData['blockGap'] ) }}
			this.customSteps = {{ Js::from( $defaultData['customSteps'] ) }}
			this.editing     = null
			this.adding      = false
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

		_isValidDimension( value ) {
			if ( '0' === value ) return true
			return /^-?\d*\.?\d+(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)$/.test( value )
		},

		_parseValue( str ) {
			if ( ! str ) return { num: '', unit: 'rem' }
			const match = String( str ).match( /^(-?\d*\.?\d+)\s*(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)?$/ )
			if ( match ) return { num: match[1], unit: match[2] || 'rem' }
			return { num: str, unit: 'rem' }
		},

		_combineValue( num, unit ) {
			if ( '' === num || null === num || undefined === num ) return ''
			return String( num ) + ( unit || 'rem' )
		},

		editNum: '',
		editUnit: 'rem',
		newNum: '',
		newUnit: 'rem',

		_syncEditFromValue() {
			const parsed = this._parseValue( this.editValue )
			this.editNum  = parsed.num
			this.editUnit = parsed.unit || 'rem'
		},

		_syncEditToValue() {
			this.editValue = this._combineValue( this.editNum, this.editUnit )
			this.editError = ''
		},

		_dispatch() {
			document.dispatchEvent( new CustomEvent( 've-spacing-change', {
				detail: {
					scale: JSON.parse( JSON.stringify( this.scale ) ),
					blockGap: this.blockGap,
					customSteps: JSON.parse( JSON.stringify( this.customSteps ) ),
				},
				bubbles: true,
			} ) );
		},

		allSteps() {
			return [ ...this.scale, ...this.customSteps ]
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-4' ] ) }}
>
	{{-- Header --}}
	<div class="flex items-center justify-between">
		<h3 class="text-sm font-semibold text-base-content">
			{{ __( 'visual-editor::ve.spacing_title' ) }}
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

	{{-- Presets --}}
	<div class="flex flex-col gap-2">
		<div class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.spacing_presets' ) }}
		</div>
		<div class="flex gap-2">
			@foreach ( $presets as $preset )
				<button
					type="button"
					x-on:click="applyPreset( '{{ $preset['key'] }}' )"
					class="btn btn-ghost btn-xs flex-1 border border-base-300"
				>
					{{ $preset['label'] }}
				</button>
			@endforeach
		</div>
	</div>

	{{-- Scale Steps --}}
	<div class="flex flex-col gap-2">
		<div class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.spacing_scale' ) }}
		</div>

		<template x-for="( entry, index ) in scale" :key="'scale-' + entry.slug">
			<div>
				{{-- Display mode --}}
				<div
					x-show="! editing || editing.index !== index || editing.isCustom"
					class="flex items-center gap-3 rounded-lg border border-base-300 px-3 py-2 hover:bg-base-200/50 focus-within:bg-base-200/50 transition-colors group"
				>
					<div
						class="h-4 rounded bg-primary/20 shrink-0"
						x-bind:style="{ '--ve-preview-size': /^-?\d*\.?\d+(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)?$/.test( entry.value ) ? entry.value : '0px' }" style="width: calc( 8px + var(--ve-preview-size, 0px) )"
					></div>
					<button
						type="button"
						class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 rounded"
						x-on:click="startEdit( index, false )"
						:aria-label="'{{ __( 'visual-editor::ve.spacing_edit_step' ) }}: ' + entry.name"
					>
						<span class="text-sm text-base-content truncate" x-text="entry.name"></span>
						<span class="text-xs text-base-content/40 font-mono" x-text="entry.value"></span>
					</button>
				</div>

				{{-- Edit mode --}}
				<div
					x-show="editing && editing.index === index && ! editing.isCustom"
					x-cloak
					class="flex flex-col gap-3 rounded-lg border border-primary/30 bg-base-200/30 px-3 py-3"
				>
					<input
						type="text"
						x-model="editName"
						x-on:input="editError = ''"
						class="input input-sm input-bordered w-full"
						placeholder="{{ __( 'visual-editor::ve.spacing_step_name' ) }}"
					/>
					<div class="flex items-center gap-2">
						<input
							type="number"
							step="0.001"
							min="0"
							x-model="editNum"
							x-on:input="editError = ''; _syncEditToValue()"
							class="input input-sm input-bordered flex-1 min-w-0 font-mono text-xs"
							placeholder="1"
						/>
						<select
							x-model="editUnit"
							x-on:change="_syncEditToValue()"
							class="select select-sm select-bordered text-xs !min-w-0 w-auto shrink-0"
						>
							<option value="rem">rem</option>
							<option value="em">em</option>
							<option value="px">px</option>
							<option value="%">%</option>
							<option value="vw">vw</option>
							<option value="vh">vh</option>
							<option value="vmin">vmin</option>
							<option value="vmax">vmax</option>
							<option value="ch">ch</option>
							<option value="ex">ex</option>
						</select>
					</div>
					<p x-show="editError" x-cloak x-text="editError" class="text-xs text-error"></p>
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

	{{-- Custom Steps --}}
	<template x-if="customSteps.length > 0">
		<div class="flex flex-col gap-2">
			<div class="text-xs font-medium text-base-content/60">
				{{ __( 'visual-editor::ve.spacing_custom_steps' ) }}
			</div>

			<template x-for="( entry, index ) in customSteps" :key="'custom-' + entry.slug">
				<div>
					{{-- Display mode --}}
					<div
						x-show="! editing || editing.index !== index || ! editing.isCustom"
						class="flex items-center gap-3 rounded-lg border border-base-300 px-3 py-2 hover:bg-base-200/50 focus-within:bg-base-200/50 transition-colors group"
					>
						<div
							class="h-4 rounded bg-accent/20 shrink-0"
							x-bind:style="{ '--ve-preview-size': /^-?\d*\.?\d+(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)?$/.test( entry.value ) ? entry.value : '0px' }" style="width: calc( 8px + var(--ve-preview-size, 0px) )"
						></div>
						<button
							type="button"
							class="flex items-center gap-2 min-w-0 flex-1 cursor-pointer text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-1 rounded"
							x-on:click="startEdit( index, true )"
							:aria-label="'{{ __( 'visual-editor::ve.spacing_edit_step' ) }}: ' + entry.name"
						>
							<span class="text-sm text-base-content truncate" x-text="entry.name"></span>
							<span class="text-xs text-base-content/40 font-mono" x-text="entry.value"></span>
						</button>
						<button
							type="button"
							x-on:click="removeCustomStep( index )"
							class="text-base-content/30 hover:text-error focus:text-error transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100 group-focus-within:opacity-100 cursor-pointer shrink-0 focus:outline-none"
							:aria-label="'{{ __( 'visual-editor::ve.spacing_remove_step' ) }}: ' + entry.name"
						>
							<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
						</button>
					</div>

					{{-- Edit mode --}}
					<div
						x-show="editing && editing.index === index && editing.isCustom"
						x-cloak
						class="flex flex-col gap-3 rounded-lg border border-primary/30 bg-base-200/30 px-3 py-3"
					>
						<div class="flex items-center gap-3">
							<input
								type="text"
								x-model="editName"
								x-on:input="editError = ''"
								class="input input-sm input-bordered flex-1 min-w-0"
								placeholder="{{ __( 'visual-editor::ve.spacing_step_name' ) }}"
							/>
							<input
								type="text"
								x-model="editValue"
								x-on:input="editError = ''"
								class="input input-sm input-bordered w-28 font-mono text-xs"
								placeholder="1rem"
							/>
						</div>
						<p x-show="editError" x-cloak x-text="editError" class="text-xs text-error"></p>
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
	</template>

	{{-- Add Custom Step --}}
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
				{{ __( 'visual-editor::ve.spacing_add_step' ) }}
			</button>
		</div>

		<div
			x-show="adding"
			x-cloak
			class="flex flex-col gap-3 rounded-lg border border-success/30 bg-base-200/30 px-3 py-3"
		>
			<input
				type="text"
				x-model="newName"
				x-on:input="newSlug = _sanitizeSlug( newName )"
				class="input input-sm input-bordered w-full"
				placeholder="{{ __( 'visual-editor::ve.spacing_step_name' ) }}"
			/>
			<div class="flex items-center gap-2">
				<input
					type="number"
					step="0.001"
					min="0"
					x-model="newNum"
					x-on:input="newValue = _combineValue( newNum, newUnit )"
					class="input input-sm input-bordered flex-1 min-w-0 font-mono text-xs"
					placeholder="1"
				/>
				<select
					x-model="newUnit"
					x-on:change="newValue = _combineValue( newNum, newUnit )"
					class="select select-sm select-bordered text-xs !min-w-0 w-auto shrink-0"
				>
					<option value="rem">rem</option>
					<option value="em">em</option>
					<option value="px">px</option>
					<option value="%">%</option>
					<option value="vw">vw</option>
					<option value="vh">vh</option>
				</select>
			</div>
			<div class="flex flex-col gap-1">
				<div class="flex items-center gap-3">
					<input
						type="text"
						x-model="newSlug"
						x-on:input="slugError = ''"
						class="input input-sm input-bordered flex-1 font-mono text-xs"
						:class="slugError ? 'input-error' : ''"
						placeholder="{{ __( 'visual-editor::ve.spacing_step_slug' ) }}"
					/>
				</div>
				<p x-show="slugError" x-cloak x-text="slugError" class="text-xs text-error"></p>
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

	{{-- Block Gap --}}
	<div class="flex flex-col gap-2">
		<div class="text-xs font-medium text-base-content/60">
			{{ __( 'visual-editor::ve.spacing_block_gap' ) }}
		</div>
		<div class="flex flex-wrap gap-1">
			<template x-for="step in allSteps()" :key="'gap-' + step.slug">
				<button
					type="button"
					x-on:click="setBlockGap( step.slug )"
					class="btn btn-xs transition-colors"
					:class="blockGap === step.slug ? 'btn-primary' : 'btn-ghost border border-base-300'"
					x-text="step.slug"
				></button>
			</template>
		</div>
		<div class="text-xs text-base-content/40">
			{{ __( 'visual-editor::ve.spacing_block_gap_description' ) }}
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
				const all = [ ...scale, ...customSteps ]
				all.forEach( ( e ) => {
					css += '  --ve-spacing-' + e.slug + ': ' + e.value + ';\n'
				} )
				css += '  --ve-block-gap: var(--ve-spacing-' + blockGap + ');\n'
				css += '}'
				return css
			})()"
		></pre>
	</div>
</div>
