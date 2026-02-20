{{--
 * Panel Body Component
 *
 * A collapsible section within a Panel with animated expand/collapse.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$contentId = $uuid . '-content';
	$headerId  = $uuid . '-header';
@endphp

<div
	id="{{ $uuid }}"
	x-data="{ open: {{ Js::from( $opened ) }} }"
	{{ $attributes->merge( [ 'class' => 'border-b border-base-300 last:border-b-0' ] ) }}
>
	{{-- Header --}}
	@if ( $collapsible )
		<button
			id="{{ $headerId }}"
			type="button"
			class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-base-200/50 transition-colors"
			x-on:click="open = ! open"
			:aria-expanded="open"
			aria-controls="{{ $contentId }}"
		>
			<span class="flex items-center gap-2">
				@if ( $icon )
					<x-artisanpack-icon :name="$icon" class="w-4 h-4 text-base-content/60" />
				@endif
				<span class="text-sm font-medium text-base-content">
					{{ $title }}
				</span>
			</span>
			<svg
				class="w-4 h-4 text-base-content/40 transition-transform duration-200"
				:class="{ 'rotate-180': open }"
				fill="none"
				viewBox="0 0 24 24"
				stroke="currentColor"
				stroke-width="2"
			>
				<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
	@else
		<div
			id="{{ $headerId }}"
			class="flex items-center gap-2 px-4 py-3"
		>
			@if ( $icon )
				<x-artisanpack-icon :name="$icon" class="w-4 h-4 text-base-content/60" />
			@endif
			<span class="text-sm font-medium text-base-content">
				{{ $title }}
			</span>
		</div>
	@endif

	{{-- Content --}}
	<div
		id="{{ $contentId }}"
		@if ( $collapsible )
			x-show="open"
			x-collapse
		@endif
		role="region"
		aria-labelledby="{{ $headerId }}"
	>
		<div class="px-4 pb-4 space-y-3">
			{{ $slot }}
		</div>
	</div>
</div>
