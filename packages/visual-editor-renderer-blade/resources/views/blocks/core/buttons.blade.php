@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// Whitelist `layout.justifyContent` against WP's known enum so an
	// authored block can't inject arbitrary class tokens through the
	// stored attribute (CodeRabbit on PR #457). Anything outside the
	// list falls back to `left`, matching WP's own default for the
	// flex layout.
	$rawJustify     = (string) ( $attributes['layout']['justifyContent'] ?? '' );
	$allowedJustify = [ 'left', 'center', 'right', 'space-between', 'space-around', 'space-evenly', 'stretch' ];
	$justify        = in_array( $rawJustify, $allowedJustify, true ) ? $rawJustify : 'left';

	$baseClasses = [
		'wp-block-buttons',
		'is-layout-flex',
		'is-content-justification-' . $justify,
	];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
