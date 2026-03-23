{{--
 * Typography Presets Editor Component
 *
 * A visual interface for managing global typography presets. Supports editing
 * font families, element-level typography styles (size, weight, line-height,
 * letter-spacing), type scale generation, and CSS custom property preview.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$initialData       = $typographyData;
	$defaultsData      = $defaultData;
	$initialBaseValues = $baseValues;
	$hasOverrideMode   = null !== $baseValues;
	$elementLabels  = [
		'h1'         => __( 'visual-editor::ve.typography_h1' ),
		'h2'         => __( 'visual-editor::ve.typography_h2' ),
		'h3'         => __( 'visual-editor::ve.typography_h3' ),
		'h4'         => __( 'visual-editor::ve.typography_h4' ),
		'h5'         => __( 'visual-editor::ve.typography_h5' ),
		'h6'         => __( 'visual-editor::ve.typography_h6' ),
		'body'       => __( 'visual-editor::ve.typography_body' ),
		'small'      => __( 'visual-editor::ve.typography_small' ),
		'caption'    => __( 'visual-editor::ve.typography_caption' ),
		'blockquote' => __( 'visual-editor::ve.typography_blockquote' ),
		'code'       => __( 'visual-editor::ve.typography_code' ),
	];
	$familyLabels = [
		'heading' => __( 'visual-editor::ve.typography_family_heading' ),
		'body'    => __( 'visual-editor::ve.typography_family_body' ),
		'mono'    => __( 'visual-editor::ve.typography_family_mono' ),
	];
	$weightOptions = [
		'100' => __( 'visual-editor::ve.typography_weight_100' ),
		'200' => __( 'visual-editor::ve.typography_weight_200' ),
		'300' => __( 'visual-editor::ve.typography_weight_300' ),
		'400' => __( 'visual-editor::ve.typography_weight_400' ),
		'500' => __( 'visual-editor::ve.typography_weight_500' ),
		'600' => __( 'visual-editor::ve.typography_weight_600' ),
		'700' => __( 'visual-editor::ve.typography_weight_700' ),
		'800' => __( 'visual-editor::ve.typography_weight_800' ),
		'900' => __( 'visual-editor::ve.typography_weight_900' ),
	];
	$typeScalePresets = [
		[ 'label' => __( 'visual-editor::ve.typography_scale_minor_third' ),  'ratio' => 1.2 ],
		[ 'label' => __( 'visual-editor::ve.typography_scale_major_third' ),  'ratio' => 1.25 ],
		[ 'label' => __( 'visual-editor::ve.typography_scale_perfect_fourth' ), 'ratio' => 1.333 ],
		[ 'label' => __( 'visual-editor::ve.typography_scale_augmented_fourth' ), 'ratio' => 1.414 ],
		[ 'label' => __( 'visual-editor::ve.typography_scale_perfect_fifth' ), 'ratio' => 1.5 ],
		[ 'label' => __( 'visual-editor::ve.typography_scale_golden_ratio' ), 'ratio' => 1.618 ],
	];
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		fontFamilies: {{ Js::from( $initialData['fontFamilies'] ) }},
		elements: {{ Js::from( $initialData['elements'] ) }},
		baseValues: {{ Js::from( $initialBaseValues ) }},
		overrideMode: {{ Js::from( $hasOverrideMode ) }},
		editingElement: null,
		activeSection: 'families',
		showCss: false,
		showTypeScale: false,
		typeScaleBase: 1,
		typeScaleRatio: 1.25,
		elementLabels: {{ Js::from( $elementLabels ) }},

		_getStore() {
			return Alpine.store( 'editor' ) || Alpine.store( 'globalStyles' ) || null;
		},

		isFamilyOverridden( slot ) {
			if ( ! this.overrideMode || ! this.baseValues?.fontFamilies ) return false;
			return this.fontFamilies[ slot ] !== this.baseValues.fontFamilies[ slot ];
		},

		isElementOverridden( element ) {
			if ( ! this.overrideMode || ! this.baseValues?.elements ) return false;
			const base    = this.baseValues.elements[ element ];
			const current = this.elements[ element ];
			if ( ! base || ! current ) return true;
			return JSON.stringify( base ) !== JSON.stringify( current );
		},

		resetFamilyToBase( slot ) {
			if ( ! this.baseValues?.fontFamilies ) return;
			this.fontFamilies[ slot ] = this.baseValues.fontFamilies[ slot ];
		},

		resetElementToBase( element ) {
			if ( ! this.baseValues?.elements || ! ( element in this.baseValues.elements ) ) return;
			this.elements[ element ] = JSON.parse( JSON.stringify( this.baseValues.elements[ element ] ) );
		},

		init() {
			const syncToStore = () => {
				const store = this._getStore();
				if ( ! store ) return;
				store.globalStyles.typography = {
					fontFamilies: JSON.parse( JSON.stringify( this.fontFamilies ) ),
					elements: JSON.parse( JSON.stringify( this.elements ) ),
				};
				store._syncGlobalCssVariables();
				store.markDirty();
				store._dispatchChange();
			};
			this.$watch( 'fontFamilies', syncToStore );
			this.$watch( 'elements', syncToStore );
		},

		setFontFamily( slot, value ) {
			this.fontFamilies[ slot ] = value
		},

		startEditElement( key ) {
			this.editingElement = this.editingElement === key ? null : key
		},

		updateElementProperty( element, property, value ) {
			if ( ! this.elements[ element ] ) return
			this.elements[ element ][ property ] = value
		},

		applyTypeScale() {
			const base    = parseFloat( this.typeScaleBase )
			const ratio   = parseFloat( this.typeScaleRatio )

			if ( ! Number.isFinite( base ) || base <= 0 || ! Number.isFinite( ratio ) || ratio <= 0 ) {
				return
			}

			const headings = [ 'h6', 'h5', 'h4', 'h3', 'h2', 'h1' ]

			headings.forEach( ( heading, index ) => {
				if ( this.elements[ heading ] ) {
					const size = base * Math.pow( ratio, index + 1 )
					this.elements[ heading ].fontSize = parseFloat( size.toFixed( 3 ) ) + 'rem'
				}
			} )
		},

		resetToDefaults() {
			const defaults     = {{ Js::from( $defaultsData ) }}
			this.fontFamilies  = JSON.parse( JSON.stringify( defaults.fontFamilies ) )
			this.elements      = JSON.parse( JSON.stringify( defaults.elements ) )
			this.editingElement = null
		},

		_getCssPreview() {
			let css = ':root {\n'
			Object.entries( this.fontFamilies ).forEach( ( [ slot, family ] ) => {
				css += '  --ve-font-' + slot + ': ' + family + ';\n'
			} )
			Object.entries( this.elements ).forEach( ( [ element, styles ] ) => {
				Object.entries( styles ).forEach( ( [ prop, value ] ) => {
					const kebab = prop.replace( /([a-z])([A-Z])/g, '$1-$2' ).toLowerCase()
					css += '  --ve-text-' + element + '-' + kebab + ': ' + value + ';\n'
				} )
			} )
			css += '}'
			return css
		},

		_parseValue( str ) {
			if ( ! str ) return { num: '', unit: 'rem' }
			const match = String( str ).match( /^(-?\d*\.?\d+)\s*(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)?$/ )
			if ( match ) return { num: match[1], unit: match[2] || 'rem' }
			return { num: str, unit: '' }
		},

		_combineValue( num, unit ) {
			if ( '' === num || null === num || undefined === num ) return ''
			return String( num ) + ( unit || '' )
		},

		_dispatch() {
			document.dispatchEvent( new CustomEvent( 've-typography-change', {
				detail: {
					fontFamilies: JSON.parse( JSON.stringify( this.fontFamilies ) ),
					elements: JSON.parse( JSON.stringify( this.elements ) ),
				},
				bubbles: true,
			} ) );
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-4' ] ) }}
>
	{{-- Header --}}
	<div class="flex items-center justify-between">
		<h3 class="text-sm font-semibold text-base-content">
			{{ __( 'visual-editor::ve.typography_title' ) }}
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

	{{-- Section Tabs --}}
	<div class="flex gap-1 rounded-lg bg-base-200 p-1" role="tablist" aria-label="{{ __( 'visual-editor::ve.typography_title' ) }}">
		<button
			type="button"
			role="tab"
			id="{{ $uuid }}-tab-families"
			aria-controls="{{ $uuid }}-panel-families"
			x-on:click="activeSection = 'families'"
			x-on:keydown.arrow-right.prevent="activeSection = 'elements'; $nextTick( () => $refs.tabElements.focus() )"
			x-on:keydown.arrow-left.prevent="activeSection = 'scale'; $nextTick( () => $refs.tabScale.focus() )"
			x-ref="tabFamilies"
			:aria-selected="activeSection === 'families'"
			:tabindex="activeSection === 'families' ? 0 : -1"
			:class="activeSection === 'families' ? 'bg-base-100 shadow-sm text-base-content' : 'text-base-content/50 hover:text-base-content/80'"
			class="flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-all cursor-pointer"
		>
			{{ __( 'visual-editor::ve.typography_fonts' ) }}
		</button>
		<button
			type="button"
			role="tab"
			id="{{ $uuid }}-tab-elements"
			aria-controls="{{ $uuid }}-panel-elements"
			x-on:click="activeSection = 'elements'"
			x-on:keydown.arrow-right.prevent="activeSection = 'scale'; $nextTick( () => $refs.tabScale.focus() )"
			x-on:keydown.arrow-left.prevent="activeSection = 'families'; $nextTick( () => $refs.tabFamilies.focus() )"
			x-ref="tabElements"
			:aria-selected="activeSection === 'elements'"
			:tabindex="activeSection === 'elements' ? 0 : -1"
			:class="activeSection === 'elements' ? 'bg-base-100 shadow-sm text-base-content' : 'text-base-content/50 hover:text-base-content/80'"
			class="flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-all cursor-pointer"
		>
			{{ __( 'visual-editor::ve.typography_elements' ) }}
		</button>
		<button
			type="button"
			role="tab"
			id="{{ $uuid }}-tab-scale"
			aria-controls="{{ $uuid }}-panel-scale"
			x-on:click="activeSection = 'scale'"
			x-on:keydown.arrow-right.prevent="activeSection = 'families'; $nextTick( () => $refs.tabFamilies.focus() )"
			x-on:keydown.arrow-left.prevent="activeSection = 'elements'; $nextTick( () => $refs.tabElements.focus() )"
			x-ref="tabScale"
			:aria-selected="activeSection === 'scale'"
			:tabindex="activeSection === 'scale' ? 0 : -1"
			:class="activeSection === 'scale' ? 'bg-base-100 shadow-sm text-base-content' : 'text-base-content/50 hover:text-base-content/80'"
			class="flex-1 rounded-md px-3 py-1.5 text-xs font-medium transition-all cursor-pointer"
		>
			{{ __( 'visual-editor::ve.typography_scale' ) }}
		</button>
	</div>

	{{-- Font Families Section --}}
	<div x-show="activeSection === 'families'" x-cloak role="tabpanel" aria-labelledby="{{ $uuid }}-tab-families" id="{{ $uuid }}-panel-families" class="flex flex-col gap-3">
		@foreach ( $familyLabels as $slot => $label )
			<div class="flex flex-col gap-1">
				<div class="flex items-center gap-1">
					<label
						for="{{ $uuid }}-family-{{ $slot }}"
						class="text-xs font-medium text-base-content/70 flex-1"
					>
						{{ $label }}
					</label>
					@if ( $hasOverrideMode )
						<template x-if="overrideMode">
							@include( 'visual-editor::components._override-indicator', [
								'overriddenExpr' => "isFamilyOverridden( '" . $slot . "' )",
								'nameExpr'       => Js::from( $label ),
								'resetExpr'      => "resetFamilyToBase( '" . $slot . "' )",
							] )
						</template>
					@endif
				</div>
				<select
					id="{{ $uuid }}-family-{{ $slot }}"
					x-model="fontFamilies.{{ $slot }}"
					x-on:change="setFontFamily( '{{ $slot }}', $event.target.value )"
					class="select select-sm select-bordered w-full text-xs"
				>
					@php
						$knownFamilies = array_keys( $availableFonts[ $slot ] ?? [] );
						$currentFamily = $typographyData['fontFamilies'][ $slot ] ?? null;
					@endphp
					@if ( $currentFamily && ! in_array( $currentFamily, $knownFamilies, true ) )
						<option value="{{ $currentFamily }}">{{ $currentFamily }}</option>
					@endif
					@foreach ( $availableFonts[ $slot ] ?? [] as $family => $name )
						<option value="{{ $family }}">{{ $name }}</option>
					@endforeach
				</select>
			</div>
		@endforeach
	</div>

	{{-- Elements Section --}}
	<div x-show="activeSection === 'elements'" x-cloak role="tabpanel" aria-labelledby="{{ $uuid }}-tab-elements" id="{{ $uuid }}-panel-elements" class="flex flex-col gap-2">
		@foreach ( $elementLabels as $key => $label )
			<div class="rounded-lg border border-base-300 overflow-hidden">
				{{-- Element row --}}
				<button
					type="button"
					x-on:click="startEditElement( '{{ $key }}' )"
					class="flex items-center justify-between w-full px-3 py-2 hover:bg-base-200/50 transition-colors cursor-pointer text-left"
					:aria-expanded="editingElement === '{{ $key }}'"
				>
					<div class="flex items-center gap-3 min-w-0">
						@if ( $hasOverrideMode )
							<template x-if="overrideMode">
								@include( 'visual-editor::components._override-indicator', [
									'overriddenExpr' => "isElementOverridden( '" . $key . "' )",
									'nameExpr'       => Js::from( $label ),
									'resetExpr'      => "resetElementToBase( '" . $key . "' )",
								] )
							</template>
						@endif
						<span
							class="shrink-0 text-base-content/70"
							:style="'font-size: ' + ( elements['{{ $key }}'] ? elements['{{ $key }}'].fontSize : '1rem' ) + '; font-weight: ' + ( elements['{{ $key }}'] ? elements['{{ $key }}'].fontWeight : '400' ) + '; line-height: 1'"
						>
							{{ mb_strtoupper( mb_substr( $label, 0, 2 ) ) }}
						</span>
						<div class="flex flex-col min-w-0">
							<span class="text-sm text-base-content truncate">{{ $label }}</span>
							<span
								class="text-xs text-base-content/40 font-mono"
								x-text="elements['{{ $key }}'] ? elements['{{ $key }}'].fontSize + ' / ' + elements['{{ $key }}'].fontWeight : ''"
							></span>
						</div>
					</div>
					<svg
						class="h-4 w-4 text-base-content/30 transition-transform shrink-0"
						:class="editingElement === '{{ $key }}' ? 'rotate-180' : ''"
						fill="none"
						stroke="currentColor"
						viewBox="0 0 24 24"
					>
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
					</svg>
				</button>

				{{-- Element edit panel --}}
				<div
					x-show="editingElement === '{{ $key }}'"
					x-cloak
					x-collapse
					class="border-t border-base-300 bg-base-200/30 px-3 py-3"
				>
					<div class="grid grid-cols-2 gap-3">
						{{-- Font Size (number + unit) --}}
						<div class="flex flex-col gap-1">
							<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider" for="{{ $uuid }}-{{ $key }}-fontSizeNum">
								{{ __( 'visual-editor::ve.typography_font_size' ) }}
							</label>
							<div class="flex gap-1">
								<input
									type="number"
									step="0.001"
									min="0"
									id="{{ $uuid }}-{{ $key }}-fontSizeNum"
									x-ref="fontSizeNum_{{ $key }}"
									:value="_parseValue( elements['{{ $key }}']?.fontSize ).num"
									x-on:change="updateElementProperty( '{{ $key }}', 'fontSize', _combineValue( $event.target.value, $refs.fontSizeUnit_{{ $key }}.value ) )"
									class="input input-sm input-bordered flex-1 min-w-0 font-mono text-xs"
									placeholder="1"
								/>
								<select
									x-ref="fontSizeUnit_{{ $key }}"
									:value="_parseValue( elements['{{ $key }}']?.fontSize ).unit"
									x-on:change="updateElementProperty( '{{ $key }}', 'fontSize', _combineValue( $refs.fontSizeNum_{{ $key }}.value, $event.target.value ) )"
									class="select select-sm select-bordered text-xs !min-w-0 w-auto shrink-0"
									aria-label="{{ __( 'visual-editor::ve.typography_font_size' ) }} {{ __( 'visual-editor::ve.unit' ) }}"
								>
									<option value="rem">rem</option>
									<option value="em">em</option>
									<option value="px">px</option>
									<option value="%">%</option>
									<option value="vw">vw</option>
								</select>
							</div>
						</div>

						{{-- Font Weight --}}
						<div class="flex flex-col gap-1">
							<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider" for="{{ $uuid }}-{{ $key }}-fontWeight">
								{{ __( 'visual-editor::ve.typography_font_weight' ) }}
							</label>
							<select
								id="{{ $uuid }}-{{ $key }}-fontWeight"
								:value="elements['{{ $key }}'] ? elements['{{ $key }}'].fontWeight : '400'"
								x-on:change="updateElementProperty( '{{ $key }}', 'fontWeight', $event.target.value )"
								class="select select-sm select-bordered w-full text-xs"
							>
								@foreach ( $weightOptions as $weightValue => $weightLabel )
									<option value="{{ $weightValue }}">{{ $weightLabel }}</option>
								@endforeach
							</select>
						</div>

						{{-- Line Height (number, unitless or with unit) --}}
						<div class="flex flex-col gap-1">
							<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider" for="{{ $uuid }}-{{ $key }}-lineHeight">
								{{ __( 'visual-editor::ve.typography_line_height' ) }}
							</label>
							<input
								type="number"
								step="0.01"
								min="0"
								id="{{ $uuid }}-{{ $key }}-lineHeight"
								:value="elements['{{ $key }}'] ? elements['{{ $key }}'].lineHeight : ''"
								x-on:change="updateElementProperty( '{{ $key }}', 'lineHeight', $event.target.value )"
								class="input input-sm input-bordered w-full font-mono text-xs"
								placeholder="1.5"
							/>
						</div>

						{{-- Letter Spacing (number + unit) --}}
						<div class="flex flex-col gap-1">
							<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider" for="{{ $uuid }}-{{ $key }}-letterSpacingNum">
								{{ __( 'visual-editor::ve.typography_letter_spacing' ) }}
							</label>
							<div class="flex gap-1">
								<input
									type="number"
									step="0.001"
									id="{{ $uuid }}-{{ $key }}-letterSpacingNum"
									x-ref="letterSpacingNum_{{ $key }}"
									:value="_parseValue( elements['{{ $key }}']?.letterSpacing ).num"
									x-on:change="updateElementProperty( '{{ $key }}', 'letterSpacing', _combineValue( $event.target.value, $refs.letterSpacingUnit_{{ $key }}.value ) )"
									class="input input-sm input-bordered flex-1 min-w-0 font-mono text-xs"
									placeholder="0"
								/>
								<select
									x-ref="letterSpacingUnit_{{ $key }}"
									:value="_parseValue( elements['{{ $key }}']?.letterSpacing ).unit"
									x-on:change="updateElementProperty( '{{ $key }}', 'letterSpacing', _combineValue( $refs.letterSpacingNum_{{ $key }}.value, $event.target.value ) )"
									class="select select-sm select-bordered text-xs !min-w-0 w-auto shrink-0"
									aria-label="{{ __( 'visual-editor::ve.typography_letter_spacing' ) }} {{ __( 'visual-editor::ve.unit' ) }}"
								>
									<option value="em">em</option>
									<option value="rem">rem</option>
									<option value="px">px</option>
								</select>
							</div>
						</div>

						{{-- Font Style --}}
						<div class="flex flex-col gap-1">
							<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider" for="{{ $uuid }}-{{ $key }}-fontStyle">
								{{ __( 'visual-editor::ve.typography_font_style' ) }}
							</label>
							<select
								id="{{ $uuid }}-{{ $key }}-fontStyle"
								:value="elements['{{ $key }}'] ? elements['{{ $key }}'].fontStyle || 'normal' : 'normal'"
								x-on:change="updateElementProperty( '{{ $key }}', 'fontStyle', $event.target.value )"
								class="select select-sm select-bordered w-full text-xs"
							>
								<option value="normal">{{ __( 'visual-editor::ve.typography_font_style_normal' ) }}</option>
								<option value="italic">{{ __( 'visual-editor::ve.typography_font_style_italic' ) }}</option>
								<option value="oblique">{{ __( 'visual-editor::ve.typography_font_style_oblique' ) }}</option>
							</select>
						</div>
					</div>

					{{-- Preview --}}
					<div class="mt-3 rounded-md bg-base-100 p-3">
						<p
							class="text-base-content/70 transition-all"
							:style="(() => {
								const el = elements['{{ $key }}']
								if ( ! el ) return ''
								let s = 'font-size: ' + el.fontSize + '; font-weight: ' + el.fontWeight + '; line-height: ' + el.lineHeight
								if ( el.letterSpacing ) s += '; letter-spacing: ' + el.letterSpacing
								if ( el.fontStyle ) s += '; font-style: ' + el.fontStyle
								return s
							})()"
						>
							{{ __( 'visual-editor::ve.typography_preview_text' ) }}
						</p>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	{{-- Type Scale Section --}}
	<div x-show="activeSection === 'scale'" x-cloak role="tabpanel" aria-labelledby="{{ $uuid }}-tab-scale" id="{{ $uuid }}-panel-scale" class="flex flex-col gap-3">
		<p class="text-xs text-base-content/50">
			{{ __( 'visual-editor::ve.typography_scale_description' ) }}
		</p>

		<div class="grid grid-cols-2 gap-3">
			<div class="flex flex-col gap-1">
				<label
					for="{{ $uuid }}-scale-base"
					class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider"
				>
					{{ __( 'visual-editor::ve.typography_scale_base_size' ) }}
				</label>
				<input
					type="number"
					id="{{ $uuid }}-scale-base"
					x-model="typeScaleBase"
					step="0.0625"
					min="0.5"
					max="3"
					class="input input-sm input-bordered w-full font-mono text-xs"
				/>
			</div>
			<div class="flex flex-col gap-1">
				<label
					for="{{ $uuid }}-scale-ratio"
					class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider"
				>
					{{ __( 'visual-editor::ve.typography_scale_ratio' ) }}
				</label>
				<input
					type="number"
					id="{{ $uuid }}-scale-ratio"
					x-model="typeScaleRatio"
					step="0.001"
					min="1"
					max="3"
					class="input input-sm input-bordered w-full font-mono text-xs"
				/>
			</div>
		</div>

		{{-- Scale presets --}}
		<div class="flex flex-wrap gap-2">
			@foreach ( $typeScalePresets as $preset )
				<button
					type="button"
					x-on:click="typeScaleRatio = {{ $preset['ratio'] }}"
					:class="Math.abs( parseFloat( typeScaleRatio ) - {{ $preset['ratio'] }} ) < 0.001 ? 'bg-primary/10 border-primary/30 text-primary' : 'border-base-300 text-base-content/50 hover:text-base-content/80'"
					class="rounded-md border px-2 py-1 text-[10px] font-medium transition-all cursor-pointer"
				>
					{{ $preset['label'] }}
				</button>
			@endforeach
		</div>

		<button
			type="button"
			x-on:click="applyTypeScale()"
			class="btn btn-primary btn-sm w-full"
		>
			{{ __( 'visual-editor::ve.typography_apply_scale' ) }}
		</button>

		{{-- Scale preview --}}
		<div class="rounded-lg border border-base-300 bg-base-200/30 p-3">
			<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
				{{ __( 'visual-editor::ve.typography_scale_preview' ) }}
			</div>
			<div class="flex flex-col gap-1">
				<template x-for="heading in [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ]" :key="heading">
					<div class="flex items-baseline gap-2">
						<span class="text-[10px] font-mono text-base-content/30 w-6" x-text="heading"></span>
						<span
							class="text-base-content/70 transition-all"
							:style="'font-size: ' + ( elements[ heading ] ? elements[ heading ].fontSize : '1rem' ) + '; line-height: 1.2'"
							x-text="elementLabels[ heading ]"
						></span>
						<span
							class="text-[10px] font-mono text-base-content/30 ml-auto"
							x-text="elements[ heading ] ? elements[ heading ].fontSize : ''"
						></span>
					</div>
				</template>
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
			x-text="_getCssPreview()"
		></pre>
	</div>
</div>
