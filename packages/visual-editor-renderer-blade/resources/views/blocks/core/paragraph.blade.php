@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$content = (string) ( $attributes['content'] ?? '' );

	// Paragraph stores its text-alignment in the `align` attribute.
	// BlockSupports::applyAlign emits both `align{value}` and
	// `has-text-align-{value}` for left/center/right; the latter is
	// the class theme stylesheets actually target for paragraphs.
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-paragraph' ] ) !!}>{!! $content !!}</p>
