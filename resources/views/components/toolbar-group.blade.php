{{--
 * Toolbar Group Component
 *
 * A logical grouping of toolbar items with visual separator.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [
		'class' => 'flex items-center gap-0.5 border-r border-base-300 pr-1 mr-0.5 last:border-r-0 last:pr-0 last:mr-0',
	] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	{{ $slot }}
</div>
