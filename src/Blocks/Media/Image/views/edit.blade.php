@php
	$url       = $content['url'] ?? '';
	$alt       = $content['alt'] ?? '';
	$caption   = $content['caption'] ?? '';
	$size      = $styles['size'] ?? 'large';
	$alignment = $styles['alignment'] ?? 'center';
	$rounded   = $styles['rounded'] ?? false;
	$shadow    = $styles['shadow'] ?? false;
	$objectFit = $styles['objectFit'] ?? 'cover';

	$classes = "ve-block ve-block-image ve-block-image--{$size} ve-block-editing text-{$alignment}";
	if ( $rounded ) {
		$classes .= ' ve-rounded';
	}
	if ( $shadow ) {
		$classes .= ' ve-shadow';
	}

	$imgStyle = "object-fit: {$objectFit};";
@endphp

<figure class="{{ $classes }}">
	@if ( $url )
		<img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}" />
	@else
		<div class="ve-block-image__placeholder">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48">
				<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
			</svg>
			<p>{{ __( 'visual-editor::ve.block_image_placeholder' ) }}</p>
		</div>
	@endif
	<figcaption
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.caption_placeholder' ) }}"
	>{!! $caption !!}</figcaption>
</figure>
