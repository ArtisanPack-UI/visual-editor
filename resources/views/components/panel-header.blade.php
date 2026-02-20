{{--
 * Panel Header Component
 *
 * A non-collapsible header for panel sections with optional tabs slot.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [
		'class' => 'sticky top-0 z-10 px-4 py-3 border-b border-base-300 bg-base-100',
	] ) }}
>
	@if ( $title )
		<h3 class="text-sm font-semibold text-base-content">
			{{ $title }}
		</h3>
	@endif

	{{ $slot }}
</div>
