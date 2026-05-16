@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-separator', 'has-alpha-channel-opacity' ];

	// Legacy schema stored the variant as `style: "wide" | "dots"`
	// (string). Modern blocks ship the variant in `className` as
	// `is-style-{variant}` via the block-style picker. Honor the
	// legacy shape here; BlockSupports normalizes the string-shaped
	// `style` attribute away (it's not the style object per-category
	// resolvers expect).
	$legacyStyle = isset( $attributes['style'] ) && is_string( $attributes['style'] ) ? $attributes['style'] : 'default';

	if ( 'wide' === $legacyStyle ) {
		$baseClasses[] = 'is-style-wide';
	} elseif ( 'dots' === $legacyStyle ) {
		$baseClasses[] = 'is-style-dots';
	}
@endphp
<hr{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}/>
