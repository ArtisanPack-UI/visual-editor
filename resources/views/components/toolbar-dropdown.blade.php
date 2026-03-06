{{--
 * Toolbar Dropdown Component
 *
 * A dropdown button for toolbars that opens a popover menu.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{ open: false }"
	class="relative"
>
	{{-- Trigger Button --}}
	<button
		type="button"
		x-on:click="open = ! open"
		{{ $attributes->merge( [
			'class' => 'flex items-center justify-center gap-1 rounded px-2 py-1.5 text-sm text-base-content/70 hover:bg-base-200 hover:text-base-content transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary' . ( $disabled ? ' opacity-40 cursor-not-allowed' : ' cursor-pointer' ),
		] ) }}
		data-ve-toolbar-item
		:aria-expanded="open"
		aria-haspopup="true"
		@if ( $disabled )
			disabled
			aria-disabled="true"
		@endif
		@if ( $tooltip )
			title="{{ $tooltip }}"
		@endif
	>
		@if ( $icon )
			<x-ve-icon :name="$icon" class="w-4 h-4" />
		@endif
		@if ( $label )
			<span>{{ $label }}</span>
		@endif
		<svg class="w-3 h-3 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
			<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
		</svg>
	</button>

	{{-- Dropdown Menu --}}
	<div
		x-show="open"
		x-on:click.outside="open = false"
		x-on:keydown.escape.window="open = false"
		x-transition:enter="transition ease-out duration-100"
		x-transition:enter-start="opacity-0 scale-95"
		x-transition:enter-end="opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-75"
		x-transition:leave-start="opacity-100 scale-100"
		x-transition:leave-end="opacity-0 scale-95"
		class="absolute {{ str_contains( $placement, 'end' ) ? 'right-0' : 'left-0' }} mt-1 z-50 min-w-48 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1"
		role="menu"
	>
		{{ $slot }}
	</div>
</div>
