{{--
 * Toolbar Button Component
 *
 * An action button for toolbars with icon, label, active state,
 * and tooltip with shortcut hint.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$baseClasses   = 'relative flex items-center justify-center rounded px-2 py-1.5 text-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary';
	if ( 'destructive' === $variant ) {
		$activeClasses = $active
			? 'bg-error/10 text-error'
			: 'text-error/70 hover:bg-error/10 hover:text-error';
	} else {
		$activeClasses = $active
			? 'bg-primary/10 text-primary'
			: 'text-base-content/70 hover:bg-base-200 hover:text-base-content';
	}

	$disabledClasses = $disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer';
@endphp

<button
	id="{{ $uuid }}"
	type="button"
	x-data="{ showTooltip: false }"
	{{ $attributes->merge( [
		'class' => $baseClasses . ' ' . $activeClasses . ' ' . $disabledClasses,
	] ) }}
	data-ve-toolbar-item
	@if ( $disabled )
		disabled
		aria-disabled="true"
	@endif
	@if ( $active )
		aria-pressed="true"
	@else
		aria-pressed="false"
	@endif
	@if ( $tooltip || $shortcut || $label )
		x-on:mouseenter="showTooltip = true"
		x-on:mouseleave="showTooltip = false"
		x-on:focus="showTooltip = true"
		x-on:blur="showTooltip = false"
	@endif
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	@if ( $icon )
		<x-artisanpack-icon :name="$icon" class="w-4 h-4" />
	@endif

	@if ( $label && ! $icon )
		<span>{{ $label }}</span>
	@endif

	{{ $slot }}

	{{-- Tooltip --}}
	@if ( $tooltip || $shortcut )
		<div
			x-show="showTooltip"
			x-transition:enter="transition ease-out duration-100"
			x-transition:enter-start="opacity-0 scale-95"
			x-transition:enter-end="opacity-100 scale-100"
			x-transition:leave="transition ease-in duration-75"
			x-transition:leave-start="opacity-100 scale-100"
			x-transition:leave-end="opacity-0 scale-95"
			class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-50 pointer-events-none"
		>
			<div class="rounded bg-neutral text-neutral-content text-xs px-2 py-1 whitespace-nowrap shadow-lg">
				@if ( $tooltip )
					<span>{{ $tooltip }}</span>
				@endif
				@if ( $shortcut )
					<kbd class="ml-1 kbd kbd-xs bg-neutral-focus text-neutral-content">{{ $shortcut }}</kbd>
				@endif
			</div>
		</div>
	@endif
</button>
