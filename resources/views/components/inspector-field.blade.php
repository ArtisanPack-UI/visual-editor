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
		? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _sv = _b?.attributes?.' . $name . '; if ( _sv !== undefined && document.activeElement !== $el ) { value = _sv; }'
		: '';

	// Build an x-show expression for conditional visibility based on
	// another block attribute's value (e.g. show mapType only when
	// provider === 'google').
	$showWhen    = $schema['showWhen'] ?? null;
	$showWhenExp = '';
	if ( $showWhen && 'dynamic' === $blockId ) {
		$conditions = [];
		foreach ( $showWhen as $depField => $depValue ) {
			$conditions[] = '$store.editor?.getBlock( $store.selection?.focused )?.attributes?.' . $depField . ' === ' . Js::from( $depValue );
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
						? 'const _b = $store.editor?.getBlock( $store.selection?.focused ); const _sv = _b?.attributes?.' . $name . '; if ( _sv !== undefined ) { checked = !! _sv; }'
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

		@case ( 'media_picker' )
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
