{{--
 * Block Placeholder Component
 *
 * Renders a standardized empty-state placeholder for media blocks
 * with icon, block name, description, and action button slot.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      2.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [ 'class' => 've-block-placeholder flex flex-col items-center justify-center gap-3 p-8 border-2 border-dashed border-base-300 rounded-lg bg-base-300/50 text-base-content' ] ) }}
>
	<div class="ve-block-placeholder__icon text-base-content/70">
		{!! $iconSvg() !!}
	</div>

	@if ( $blockName )
		<p class="ve-block-placeholder__name font-medium text-base-content">{{ $blockName }}</p>
	@endif

	@if ( $description )
		<p class="ve-block-placeholder__description text-sm text-base-content/70">{{ $description }}</p>
	@endif

	@if ( $slot->isNotEmpty() )
		<div class="ve-block-placeholder__actions flex gap-2">
			{{ $slot }}
		</div>
	@endif
</div>
