@php
	$images         = $content['images'] ?? [];
	$columns        = $styles['columns'] ?? '3';
	$gap            = $styles['gap'] ?? 'medium';
	$captionDisplay = $styles['captionDisplay'] ?? 'below';
	$crop           = $styles['crop'] ?? true;
	$anchor         = $content['anchor'] ?? null;
	$htmlId         = $content['htmlId'] ?? null;
	$className      = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$gapMap = [ 'none' => '0', 'small' => '0.5rem', 'medium' => '1rem', 'large' => '1.5rem' ];
	$gapValue = $gapMap[ $gap ] ?? '1rem';

	$classes = "ve-block ve-block-gallery ve-block-gallery--captions-{$captionDisplay}";
	if ( $crop ) {
		$classes .= ' ve-block-gallery--crop';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<figure
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<div
		class="ve-block-gallery__grid"
		style="display: grid; grid-template-columns: repeat({{ $columns }}, 1fr); gap: {{ $gapValue }};"
	>
		@foreach ( $images as $image )
			<figure class="ve-block-gallery__item">
				<img
					src="{{ $image['url'] ?? '' }}"
					alt="{{ $image['alt'] ?? '' }}"
					@if ( $crop ) style="object-fit: cover; aspect-ratio: 1;" @endif
				/>
				@if ( 'none' !== $captionDisplay && ( $image['caption'] ?? '' ) )
					<figcaption class="ve-block-gallery__caption">{!! $image['caption'] !!}</figcaption>
				@endif
			</figure>
		@endforeach
	</div>
</figure>
