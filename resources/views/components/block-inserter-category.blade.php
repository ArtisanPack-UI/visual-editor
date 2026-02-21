{{--
 * Block Inserter Category Component
 *
 * A category section header and filter within the block inserter.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [ 'class' => 'mb-3' ] ) }}
	role="group"
	aria-label="{{ $label ?? $name }}"
>
	{{-- Category header --}}
	<h3 class="text-xs font-semibold text-base-content/60 uppercase tracking-wide px-2 mb-1.5">
		@if ( $icon )
			<span class="inline-flex items-center gap-1.5">
				<x-artisanpack-icon :name="$icon" class="w-3.5 h-3.5" />
				{{ $label ?? $name }}
			</span>
		@else
			{{ $label ?? $name }}
		@endif

		@if ( $count > 0 )
			<span class="text-base-content/40 font-normal ml-1">({{ $count }})</span>
		@endif
	</h3>

	{{-- Block items slot --}}
	<div class="grid grid-cols-3 gap-1">
		{{ $slot }}
	</div>
</div>
