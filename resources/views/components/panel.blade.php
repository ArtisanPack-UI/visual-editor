{{--
 * Panel Component
 *
 * A scrollable container for organizing inspector panel sections.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [
		'class' => 'flex flex-col border border-base-300 rounded-lg bg-base-100 overflow-hidden',
	] ) }}
	style="max-height: {{ $maxHeight }}"
	role="region"
	@if ( $title )
		aria-label="{{ $title }}"
	@endif
>
	@if ( $title )
		<div class="px-4 py-3 border-b border-base-300 bg-base-200/50">
			<h3 class="text-sm font-semibold text-base-content">
				{{ $title }}
			</h3>
		</div>
	@endif

	<div class="flex-1 overflow-y-auto">
		{{ $slot }}
	</div>
</div>
