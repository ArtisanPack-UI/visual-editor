{{--
 * Inspector Field Component
 *
 * Renders the appropriate control based on schema field type.
 * Dispatches ve-field-change events when values change.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$fieldType        = $fieldType();
	$fieldLabel       = $fieldLabel();
	$fieldPlaceholder = $fieldPlaceholder();
	$fieldOptions     = $fieldOptions();
	$fieldDefault     = $fieldDefault();
	$fieldHint        = $fieldHint();
	$currentValue     = $value ?? $fieldDefault;

	// Build an x-effect expression that syncs the field value from the
	// Alpine editor store.  This keeps inspector fields up-to-date when
	// block attributes are changed programmatically (e.g. geocoding).
	// The activeElement check prevents overwriting user input while the
	// field is focused.  Writing to value without reading it avoids
	// creating a dependency on the local state (only store changes
	// trigger re-evaluation).
	$storeSync = 'dynamic' === $blockId
		? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _sv = _b?.attributes?.[' . Js::from( $name ) . ']; if ( _sv !== undefined && document.activeElement !== $el ) { value = _sv; }'
		: '';

	// Build an x-show expression for conditional visibility based on
	// another block attribute's value (e.g. show mapType only when
	// provider === 'google').
	$showWhen    = $schema['showWhen'] ?? null;
	$showWhenExp = '';
	if ( $showWhen && 'dynamic' === $blockId ) {
		$conditions = [];
		foreach ( $showWhen as $depField => $depValue ) {
			$conditions[] = '$store.editor?.getBlock( $store.selection?.focused )?.attributes?.[' . Js::from( $depField ) . '] === ' . Js::from( $depValue );
		}
		$showWhenExp = implode( ' && ', $conditions );
	}
@endphp

<div
	id="{{ $uuid }}"
	@if ( $showWhenExp ) x-show="{{ $showWhenExp }}" x-cloak @endif
	{{ $attributes->merge( [ 'class' => 've-inspector-field' ] ) }}
>
	@switch ( $fieldType )
		@case ( 'color' )
			<x-ve-color-system
				:label="$fieldLabel"
				:value="$currentValue"
				:compact="true"
				x-on:ve-color-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail.color } )"
			/>
			@break

		@case ( 'border' )
			@php
				$borderValue = is_array( $currentValue ) ? $currentValue : [];
			@endphp
			<x-ve-border-control
				:label="$fieldLabel"
				:width="$borderValue['width'] ?? '0'"
				:widthUnit="$borderValue['widthUnit'] ?? 'px'"
				:style="$borderValue['style'] ?? 'none'"
				:color="$borderValue['color'] ?? '#000000'"
				:radius="$borderValue['radius'] ?? '0'"
				:radiusUnit="$borderValue['radiusUnit'] ?? 'px'"
				:perSide="$borderValue['perSide'] ?? false"
				:perCorner="$borderValue['perCorner'] ?? false"
				x-on:ve-border-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail } )"
			/>
			@break

		@case ( 'alignment' )
			<x-ve-alignment-control
				:label="$fieldLabel"
				:value="$currentValue ?? 'left'"
				x-on:ve-alignment-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail.alignment } )"
			/>
			@break

		@case ( 'spacing' )
			@php
				$spacingSides = $schema['sides'] ?? [ 'top', 'right', 'bottom', 'left' ];
			@endphp
			<x-ve-box-control
				:label="$fieldLabel"
				:sides="$spacingSides"
				:values="is_array( $currentValue ) ? $currentValue : []"
				x-on:ve-box-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail } )"
			/>
			@break

		@case ( 'select' )
			<div class="ve-inspector-field-select">
				<label class="text-sm font-medium text-base-content/80 block mb-1" for="{{ $uuid }}-select">
					{{ $fieldLabel }}
				</label>
				<select
					id="{{ $uuid }}-select"
					class="select select-bordered select-sm w-full"
					x-data="{ value: {{ Js::from( $currentValue ) }} }"
					x-model="value"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				>
					@foreach ( $fieldOptions as $optionValue => $optionLabel )
						<option value="{{ $optionValue }}" @if ( $optionValue === $currentValue ) selected @endif>
							{{ $optionLabel }}
						</option>
					@endforeach
				</select>
			</div>
			@break

		@case ( 'toggle' )
			<div class="ve-inspector-field-toggle flex items-center justify-between">
				<label class="text-sm font-medium text-base-content/80" for="{{ $uuid }}-toggle">
					{{ $fieldLabel }}
				</label>
				@php
					$toggleSync = 'dynamic' === $blockId
						? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _sv = _b?.attributes?.[' . Js::from( $name ) . ']; if ( _sv !== undefined ) { checked = !! _sv; }'
						: '';
				@endphp
				<input
					type="checkbox"
					id="{{ $uuid }}-toggle"
					class="toggle toggle-sm toggle-primary"
					x-data="{ checked: {{ Js::from( (bool) $currentValue ) }} }"
					x-model="checked"
					@if ( $toggleSync ) x-effect="{{ $toggleSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: checked } )"
					@if ( $currentValue ) checked @endif
				/>
			</div>
			@break

		@case ( 'range' )
			@php
				$min  = $schema['min'] ?? 0;
				$max  = $schema['max'] ?? 100;
				$step = $schema['step'] ?? 1;
			@endphp
			<x-ve-range-control
				:label="$fieldLabel"
				:value="$currentValue ?? $min"
				:min="$min"
				:max="$max"
				:step="$step"
				x-on:ve-range-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail.value } )"
			/>
			@break

		@case ( 'responsive_range' )
			@php
				$rrMin    = $schema['min'] ?? 0;
				$rrMax    = $schema['max'] ?? 100;
				$rrStep   = $schema['step'] ?? 1;
				$rrValues = is_array( $currentValue )
				? array_merge( [ 'mode' => 'global', 'global' => $currentValue['desktop'] ?? $rrMin ], $currentValue )
				: [ 'mode' => 'global', 'global' => $currentValue ?? $rrMin, 'desktop' => $currentValue ?? $rrMin, 'tablet' => $currentValue ?? $rrMin, 'mobile' => $currentValue ?? $rrMin ];
			@endphp
			<x-ve-responsive-range-control
				:label="$fieldLabel"
				:value="$rrValues"
				:min="$rrMin"
				:max="$rrMax"
				:step="$rrStep"
				x-on:ve-responsive-range-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail.values } )"
			/>
			@break

		@case ( 'font_size' )
			<x-ve-font-size-picker
				:label="$fieldLabel"
				:value="$currentValue"
				x-on:ve-font-size-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $event.detail.value } )"
			/>
			@break

		@case ( 'font_family' )
			@php
				$typographyManager   = app( 'visual-editor.typography-presets' );
				$fontCategoryFilter  = $schema['fontCategory'] ?? null;
				$registeredFonts     = $typographyManager->getFontOptions( $fontCategoryFilter );
				$familyOptions       = [ '' => __( 'visual-editor::ve.typography_default' ) ] + $registeredFonts;
			@endphp
			<div class="ve-inspector-field-font-family">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1" for="{{ $uuid }}-font-family">
					{{ $fieldLabel }}
				</label>
				<select
					id="{{ $uuid }}-font-family"
					class="select select-bordered select-sm w-full"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					x-model="value"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				>
					@foreach ( $familyOptions as $optionValue => $optionLabel )
						<option value="{{ $optionValue }}">{{ $optionLabel }}</option>
					@endforeach
				</select>
			</div>
			@break

		@case ( 'appearance' )
			@php
				$appearanceOptions = [
					''                   => __( 'visual-editor::ve.typography_default' ),
					'100'                => __( 'visual-editor::ve.typography_appearance_thin' ),
					'200'                => __( 'visual-editor::ve.typography_appearance_extra_light' ),
					'300'                => __( 'visual-editor::ve.typography_appearance_light' ),
					'400'                => __( 'visual-editor::ve.typography_appearance_regular' ),
					'500'                => __( 'visual-editor::ve.typography_appearance_medium' ),
					'600'                => __( 'visual-editor::ve.typography_appearance_semi_bold' ),
					'700'                => __( 'visual-editor::ve.typography_appearance_bold' ),
					'800'                => __( 'visual-editor::ve.typography_appearance_extra_bold' ),
					'900'                => __( 'visual-editor::ve.typography_appearance_black' ),
					'100-italic'         => __( 'visual-editor::ve.typography_appearance_thin_italic' ),
					'200-italic'         => __( 'visual-editor::ve.typography_appearance_extra_light_italic' ),
					'300-italic'         => __( 'visual-editor::ve.typography_appearance_light_italic' ),
					'400-italic'         => __( 'visual-editor::ve.typography_appearance_regular_italic' ),
					'500-italic'         => __( 'visual-editor::ve.typography_appearance_medium_italic' ),
					'600-italic'         => __( 'visual-editor::ve.typography_appearance_semi_bold_italic' ),
					'700-italic'         => __( 'visual-editor::ve.typography_appearance_bold_italic' ),
					'800-italic'         => __( 'visual-editor::ve.typography_appearance_extra_bold_italic' ),
					'900-italic'         => __( 'visual-editor::ve.typography_appearance_black_italic' ),
				];
			@endphp
			<div class="ve-inspector-field-appearance">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1" for="{{ $uuid }}-appearance">
					{{ $fieldLabel }}
				</label>
				<select
					id="{{ $uuid }}-appearance"
					class="select select-bordered select-sm w-full"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					x-model="value"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				>
					@foreach ( $appearanceOptions as $optionValue => $optionLabel )
						<option value="{{ $optionValue }}">{{ $optionLabel }}</option>
					@endforeach
				</select>
			</div>
			@break

		@case ( 'line_height' )
			@php
				$lhStoreSync = 'dynamic' === $blockId
					? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _sv = _b?.attributes?.[' . Js::from( $name ) . ']; if ( _sv !== undefined && ! $el.contains( document.activeElement ) ) { value = _sv; }'
					: '';
			@endphp
			<div class="ve-inspector-field-line-height">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1" for="{{ $uuid }}-line-height">
					{{ $fieldLabel }}
				</label>
				<div
					class="flex items-center gap-1"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					@if ( $lhStoreSync ) x-effect="{{ $lhStoreSync }}" @endif
				>
					<input
						type="number"
						id="{{ $uuid }}-line-height"
						class="input input-bordered input-sm w-full font-mono text-xs"
						step="0.1"
						min="0"
						max="10"
						placeholder="1.5"
						x-model="value"
						x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
					/>
					<button
						type="button"
						class="btn btn-ghost btn-sm btn-square shrink-0"
						x-on:click="value = Math.min( 10, parseFloat( value !== '' && value !== null ? value : 1.5 ) + 0.1 ); value = parseFloat( value.toFixed( 1 ) ); $dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: String( value ) } )"
						aria-label="{{ __( 'visual-editor::ve.typography_increase' ) }}"
					>
						<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
					</button>
					<button
						type="button"
						class="btn btn-ghost btn-sm btn-square shrink-0"
						x-on:click="value = Math.max( 0, parseFloat( value !== '' && value !== null ? value : 1.5 ) - 0.1 ); value = parseFloat( value.toFixed( 1 ) ); $dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: String( value ) } )"
						aria-label="{{ __( 'visual-editor::ve.typography_decrease' ) }}"
					>
						<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
					</button>
				</div>
			</div>
			@break

		@case ( 'letter_spacing' )
			@php
				$lsNumeric = '';
				$lsUnit    = 'px';
				$lsRaw     = $currentValue ?? '';

				if ( is_numeric( $lsRaw ) ) {
					$lsNumeric = (string) $lsRaw;
				} elseif ( is_string( $lsRaw ) && '' !== $lsRaw ) {
					if ( preg_match( '/^(-?\d+\.?\d*)\s*([a-z%]+)$/i', $lsRaw, $lsMatch ) ) {
						$lsNumeric = $lsMatch[1];
						$lsUnit    = $lsMatch[2];
					}
				}

				$lsStoreSync = 'dynamic' === $blockId
					? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _raw = _b?.attributes?.[' . Js::from( $name ) . ']; if ( _raw !== undefined && ! $el.contains( document.activeElement ) ) { const _m = String( _raw ).match( /^(-?\\d+\\.?\\d*)\\s*([a-z%]+)$/i ); if ( _m ) { value = _m[1]; unit = _m[2]; } else if ( _raw !== \'\' && ! isNaN( parseFloat( _raw ) ) ) { value = String( _raw ); unit = \'px\'; } else { value = \'\'; unit = \'px\'; } }'
					: '';
			@endphp
			<div class="ve-inspector-field-letter-spacing">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1" for="{{ $uuid }}-letter-spacing">
					{{ $fieldLabel }}
				</label>
				<div
					class="flex items-center gap-1"
					x-data="{ value: {{ Js::from( $lsNumeric ) }}, unit: {{ Js::from( $lsUnit ) }} }"
					@if ( $lsStoreSync ) x-effect="{{ $lsStoreSync }}" @endif
				>
					<input
						type="number"
						id="{{ $uuid }}-letter-spacing"
						class="input input-bordered input-sm flex-1 font-mono text-xs"
						step="0.5"
						placeholder="0"
						x-model="value"
						x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: ( value !== '' && value !== null ) ? value + unit : '' } )"
					/>
					<span class="text-xs text-base-content/40 font-mono shrink-0 w-6 text-center" x-text="unit"></span>
				</div>
			</div>
			@break

		@case ( 'decoration' )
			@php
				$decorationOptions = [
					[ 'value' => '',             'label' => __( 'visual-editor::ve.typography_decoration_none' ),          'icon' => 'M20 12H4' ],
					[ 'value' => 'underline',    'label' => __( 'visual-editor::ve.typography_decoration_underline' ),     'icon' => 'M6 3v7a6 6 0 0012 0V3M4 21h16' ],
					[ 'value' => 'line-through', 'label' => __( 'visual-editor::ve.typography_decoration_strikethrough' ), 'icon' => 'M16 4H9a3 3 0 100 6h6a3 3 0 010 6H8M4 12h16' ],
				];
			@endphp
			@php
				$decoValues = array_map( fn ( $o ) => $o['value'], $decorationOptions );
			@endphp
			<div class="ve-inspector-field-decoration">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1">
					{{ $fieldLabel }}
				</label>
				<div
					class="flex gap-1"
					x-data="{
						value: {{ Js::from( $currentValue ?? '' ) }},
						options: {{ Js::from( $decoValues ) }},
						_move( delta ) {
							const idx = this.options.indexOf( this.value )
							const next = ( idx + delta + this.options.length ) % this.options.length
							this.value = this.options[ next ]
							this.$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: this.value } )
							this.$nextTick( () => this.$el.querySelectorAll( '[role=radio]' )[ next ]?.focus() )
						},
					}"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					role="radiogroup"
					aria-label="{{ $fieldLabel }}"
					x-on:keydown.arrow-right.prevent="_move( 1 )"
					x-on:keydown.arrow-down.prevent="_move( 1 )"
					x-on:keydown.arrow-left.prevent="_move( -1 )"
					x-on:keydown.arrow-up.prevent="_move( -1 )"
				>
					@foreach ( $decorationOptions as $option )
						<button
							type="button"
							role="radio"
							class="btn btn-ghost btn-sm btn-square transition-colors"
							:class="value === {{ Js::from( $option['value'] ) }} ? 'bg-base-300 text-base-content' : 'text-base-content/40'"
							x-on:click="value = {{ Js::from( $option['value'] ) }}; $dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
							:aria-checked="value === {{ Js::from( $option['value'] ) }}"
							:tabindex="value === {{ Js::from( $option['value'] ) }} ? 0 : -1"
							aria-label="{{ $option['label'] }}"
						>
							<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" d="{{ $option['icon'] }}" />
							</svg>
						</button>
					@endforeach
				</div>
			</div>
			@break

		@case ( 'letter_case' )
			@php
				$caseOptions = [
					[ 'value' => '',           'label' => __( 'visual-editor::ve.typography_case_none' ),       'text' => '—' ],
					[ 'value' => 'uppercase',  'label' => __( 'visual-editor::ve.typography_case_uppercase' ),  'text' => 'AB' ],
					[ 'value' => 'lowercase',  'label' => __( 'visual-editor::ve.typography_case_lowercase' ),  'text' => 'ab' ],
					[ 'value' => 'capitalize', 'label' => __( 'visual-editor::ve.typography_case_capitalize' ), 'text' => 'Ab' ],
				];
			@endphp
			@php
				$caseValues = array_map( fn ( $o ) => $o['value'], $caseOptions );
			@endphp
			<div class="ve-inspector-field-letter-case">
				<label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wider block mb-1">
					{{ $fieldLabel }}
				</label>
				<div
					class="flex gap-1"
					x-data="{
						value: {{ Js::from( $currentValue ?? '' ) }},
						options: {{ Js::from( $caseValues ) }},
						_move( delta ) {
							const idx = this.options.indexOf( this.value )
							const next = ( idx + delta + this.options.length ) % this.options.length
							this.value = this.options[ next ]
							this.$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: this.value } )
							this.$nextTick( () => this.$el.querySelectorAll( '[role=radio]' )[ next ]?.focus() )
						},
					}"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					role="radiogroup"
					aria-label="{{ $fieldLabel }}"
					x-on:keydown.arrow-right.prevent="_move( 1 )"
					x-on:keydown.arrow-down.prevent="_move( 1 )"
					x-on:keydown.arrow-left.prevent="_move( -1 )"
					x-on:keydown.arrow-up.prevent="_move( -1 )"
				>
					@foreach ( $caseOptions as $option )
						<button
							type="button"
							role="radio"
							class="btn btn-ghost btn-sm px-2 text-xs font-medium transition-colors"
							:class="value === {{ Js::from( $option['value'] ) }} ? 'bg-base-300 text-base-content' : 'text-base-content/40'"
							x-on:click="value = {{ Js::from( $option['value'] ) }}; $dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
							:aria-checked="value === {{ Js::from( $option['value'] ) }}"
							:tabindex="value === {{ Js::from( $option['value'] ) }} ? 0 : -1"
							aria-label="{{ $option['label'] }}"
						>
							{{ $option['text'] }}
						</button>
					@endforeach
				</div>
			</div>
			@break

		@case ( 'rich_text' )
			<div class="ve-inspector-field-rich-text">
				<label class="text-sm font-medium text-base-content/80 block mb-1">
					{{ $fieldLabel }}
				</label>
				<div
					class="textarea textarea-bordered min-h-20 w-full"
					contenteditable="true"
					x-data="{ content: {{ Js::from( $currentValue ?? '' ) }} }"
					x-on:input="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: $el.innerHTML } )"
					data-placeholder="{{ $fieldPlaceholder }}"
				>{!! $currentValue ?? '' !!}</div>
			</div>
			@break

		@case ( 'unit' )
			@php
				$unitCurrentValue = $currentValue ?? '';
				$unitNumeric      = '';
				$unitSuffix       = 'px';

				if ( is_numeric( $unitCurrentValue ) ) {
					$unitNumeric = (string) $unitCurrentValue;
				} elseif ( is_string( $unitCurrentValue ) && '' !== $unitCurrentValue ) {
					if ( preg_match( '/^(\d+\.?\d*|\.\d+)\s*([a-z%]+)$/i', $unitCurrentValue, $m ) ) {
						$unitNumeric = $m[1];
						$unitSuffix  = $m[2];
					} else {
						$unitNumeric = $unitCurrentValue;
					}
				}
			@endphp
			<x-ve-unit-control
				:label="$fieldLabel"
				:value="$unitNumeric"
				:unit="$unitSuffix"
				x-on:ve-unit-change.stop="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: ( $event.detail.value !== null && $event.detail.value !== undefined && $event.detail.value !== '' ) ? ( $event.detail.value + $event.detail.unit ) : '' } )"
			/>
			@break

		@case ( 'media_picker' )
			@php
				$mediaStoreSync = 'dynamic' === $blockId
					? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); value = _b?.attributes?.[' . Js::from( $name ) . '] || \'\'; const _fid = $store.selection?.focused || \'dynamic\'; context = _fid + \':\' + ' . Js::from( $name ) . ';'
					: '';
			@endphp
			<div
				class="ve-inspector-field-media-picker"
				x-data="{
					value: {{ Js::from( $currentValue ?? '' ) }},
					context: {{ Js::from( $blockId . ':' . $name ) }},
					selectMedia() {
						Livewire.dispatch( 'open-ve-media-picker', { context: this.context } );
					},
					removeMedia() {
						this.value = '';
						$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: '' } );
					}
				}"
				@if ( $mediaStoreSync ) x-effect="{{ $mediaStoreSync }}" @endif
				x-on:ve-media-selected.window="
					if ( $event.detail.context === context && $event.detail.media && $event.detail.media.length ) {
						value = $event.detail.media[0].url ?? $event.detail.media[0].path ?? '';
						$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } );
					}
				"
			>
				<label class="text-sm font-medium text-base-content/80 block mb-1">
					{{ $fieldLabel }}
				</label>

				<template x-if="value">
					<div class="space-y-2">
						<div class="relative rounded-lg overflow-hidden border border-base-300">
							<img :src="value" alt="" class="w-full h-32 object-cover" />
						</div>
						<div class="flex gap-2">
							<button type="button" class="btn btn-sm btn-ghost flex-1" x-on:click="selectMedia()">
								{{ __( 'visual-editor::ve.replace_image' ) }}
							</button>
							<button type="button" class="btn btn-sm btn-ghost text-error flex-1" x-on:click="removeMedia()">
								{{ __( 'visual-editor::ve.remove_image' ) }}
							</button>
						</div>
					</div>
				</template>

				<template x-if="! value">
					<button type="button" class="btn btn-sm btn-outline w-full" x-on:click="selectMedia()">
						{{ __( 'visual-editor::ve.select_media' ) }}
					</button>
				</template>
			</div>
			@break

		@case ( 'textarea' )
			<div class="ve-inspector-field-textarea">
				<label class="text-sm font-medium text-base-content/80 block mb-1" for="{{ $uuid }}-textarea">
					{{ $fieldLabel }}
				</label>
				<textarea
					id="{{ $uuid }}-textarea"
					class="textarea textarea-bordered textarea-sm w-full"
					placeholder="{{ $fieldPlaceholder }}"
					rows="3"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					x-model="value"
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				>{{ $currentValue ?? '' }}</textarea>
				@if ( $fieldHint )
					<p class="text-xs text-base-content/50 mt-1">{{ $fieldHint }}</p>
				@endif
			</div>
			@break

		@case ( 'url' )
			<div class="ve-inspector-field-url">
				<label class="text-sm font-medium text-base-content/80 block mb-1" for="{{ $uuid }}-url">
					{{ $fieldLabel }}
				</label>
				<input
					type="url"
					id="{{ $uuid }}-url"
					class="input input-bordered input-sm w-full"
					placeholder="{{ $fieldPlaceholder }}"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					x-model="value"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				/>
			</div>
			@break

		@default
			{{-- Default: text input --}}
			<div class="ve-inspector-field-text">
				<label class="text-sm font-medium text-base-content/80 block mb-1" for="{{ $uuid }}-text">
					{{ $fieldLabel }}
				</label>
				<input
					type="text"
					id="{{ $uuid }}-text"
					class="input input-bordered input-sm w-full"
					placeholder="{{ $fieldPlaceholder }}"
					x-data="{ value: {{ Js::from( $currentValue ?? '' ) }} }"
					x-model="value"
					@if ( $storeSync ) x-effect="{{ $storeSync }}" @endif
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				/>
				@if ( $fieldHint )
					<p class="text-xs text-base-content/50 mt-1">{{ $fieldHint }}</p>
				@endif
			</div>
	@endswitch
</div>
