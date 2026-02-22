@php
	$images         = $content['images'] ?? [];
	$columns        = $styles['columns'] ?? '3';
	$gap            = $styles['gap'] ?? 'medium';
	$captionDisplay = $styles['captionDisplay'] ?? 'below';
	$crop           = $styles['crop'] ?? true;

	$gapMap   = [ 'none' => '0', 'small' => '0.5rem', 'medium' => '1rem', 'large' => '1.5rem' ];
	$gapValue = $gapMap[ $gap ] ?? '1rem';

	$classes = "ve-block ve-block-gallery ve-block-gallery--captions-{$captionDisplay} ve-block-editing";
	if ( $crop ) {
		$classes .= ' ve-block-gallery--crop';
	}
@endphp

<figure class="{{ $classes }}">
	<div
		class="ve-block-gallery__grid"
		style="display: grid; grid-template-columns: repeat({{ $columns }}, 1fr); gap: {{ $gapValue }};"
	>
		@forelse ( $images as $image )
			<figure class="ve-block-gallery__item">
				<img
					src="{{ $image['url'] ?? '' }}"
					alt="{{ $image['alt'] ?? '' }}"
					@if ( $crop ) style="object-fit: cover; aspect-ratio: 1;" @endif
				/>
			</figure>
		@empty
			<div class="ve-block-gallery__placeholder">
				<p>{{ __( 'visual-editor::ve.block_gallery_placeholder' ) }}</p>
			</div>
		@endforelse
	</div>
</figure>
