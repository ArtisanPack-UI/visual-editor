@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// block.json declares `supports.layout.default.type = "flex"`. The
	// editor's `useBlockProps` adds the `is-layout-flex` +
	// `wp-block-gallery-is-layout-flex` classes automatically from that
	// declaration; the front-end Blade renderer has to mirror it here
	// because BlockSupports::compile() doesn't (yet) walk the layout
	// support. Without these classes the gallery falls back to block
	// flow and images stack vertically instead of laying out as a grid.
	$baseClasses = [
		'wp-block-gallery',
		'has-nested-images',
		'is-layout-flex',
		'wp-block-gallery-is-layout-flex',
	];

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
