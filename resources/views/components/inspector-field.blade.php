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
	$currentValue     = $value ?? $fieldDefault;
@endphp

<div
	id="{{ $uuid }}"
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
				<input
					type="checkbox"
					id="{{ $uuid }}-toggle"
					class="toggle toggle-sm toggle-primary"
					x-data="{ checked: {{ Js::from( (bool) $currentValue ) }} }"
					x-model="checked"
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
					x-on:change="$dispatch( 've-field-change', { blockId: {{ Js::from( $blockId ) }}, field: {{ Js::from( $name ) }}, value: value } )"
				/>
			</div>
	@endswitch
</div>
