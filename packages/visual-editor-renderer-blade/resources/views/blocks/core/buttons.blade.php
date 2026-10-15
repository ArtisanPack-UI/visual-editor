@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// Whitelist `layout.justifyContent` against WP's known enum so an
	// authored block can't inject arbitrary class tokens through the
	// stored attribute (CodeRabbit on PR #457). Anything outside the
	// list falls back to `left`, matching WP's own default for the
	// flex layout.
	$rawJustify     = (string) ( $attributes['layout']['justifyContent'] ?? '' );
	$allowedJustify = [ 'left', 'center', 'right', 'space-between', 'space-around', 'space-evenly', 'stretch' ];
	$justify        = in_array( $rawJustify, $allowedJustify, true ) ? $rawJustify : 'left';

	// F-3 — buttons is always a flex layout (block.json disallows
	// switching). Its orientation, wrap, justification, vertical
	// alignment and Block spacing ride on a hashed `wp-container-*`
	// scope, as for the group, followed by the `is-vertical` /
	// `is-nowrap` modifiers. The justification class stays the
	// block's own, with its `left` default.
	$baseClasses = array_merge(
		[ 'wp-block-buttons' ],
		LayoutSupport::pair( 'buttons', 'is-layout-flex' ),
		LayoutSupport::containerClasses( $attributes, 'buttons', 'is-layout-flex' ),
		[ 'is-content-justification-' . $justify ],
		LayoutSupport::modifierClasses( $attributes, true, false )
	);
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
