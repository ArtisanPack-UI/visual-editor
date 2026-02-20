{{--
 * Panel Row Component
 *
 * A label + control layout row within a PanelBody section.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	@if ( $fullWidth )
		<div>
			{{ $slot }}
		</div>
	@else
		<div class="flex items-center justify-between gap-4">
			@if ( $label )
				<label class="text-xs font-medium text-base-content/60 shrink-0">
					{{ $label }}
				</label>
			@endif
			<div class="flex-1 min-w-0">
				{{ $slot }}
			</div>
		</div>
	@endif

	@if ( $help )
		<p class="mt-1 text-xs text-base-content/40">
			{{ $help }}
		</p>
	@endif
</div>
