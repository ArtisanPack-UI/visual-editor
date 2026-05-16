@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-gallery', 'has-nested-images' ];

	if ( ! empty( $attributes['columns'] ) ) {
		$baseClasses[] = 'columns-' . (int) $attributes['columns'];
	}

	if ( ! empty( $attributes['imageCrop'] ) ) {
		$baseClasses[] = 'is-cropped';
	}

	$caption = (string) ( $attributes['caption'] ?? '' );
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
	@if ( '' !== trim( $caption ) )
		<figcaption class="blocks-gallery-caption">{!! $caption !!}</figcaption>
	@endif
</figure>
