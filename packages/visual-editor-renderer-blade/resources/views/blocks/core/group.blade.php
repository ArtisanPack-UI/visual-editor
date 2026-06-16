@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;

	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$layoutType = isset( $attributes['layout']['type'] ) ? (string) $attributes['layout']['type'] : '';

	if ( 'constrained' === $layoutType ) {
		$layoutClass = 'is-layout-constrained';
	} elseif ( 'flex' === $layoutType ) {
		$layoutClass = 'is-layout-flex';
	} elseif ( 'grid' === $layoutType ) {
		$layoutClass = 'is-layout-grid';
	} else {
		$layoutClass = 'is-layout-flow';
	}

	$flexClasses = FlexSupport::wrapperForBlock( $attributes );

	// #595 — when our Flex Layout panel is active at the base breakpoint,
	// switch the layout class to `is-layout-flex` so the baseline
	// `is-layout-flow > * + * { margin-block-start: gap }` rule does
	// not push children apart along the cross axis.
	//
	// Match only the unprefixed `ap-flex` (the base-breakpoint emit).
	// Breakpoint-prefixed variants like `md:ap-flex` mean "flex starts
	// at md+"; flipping the wrapper to `is-layout-flex` for them would
	// apply flex below the breakpoint too. The matching reset comes
	// from the defensive `:where(.md\:ap-flex) > * + * { margin-block-start: 0 }`
	// rule in flex-layout.css scoped to its own media query.
	$hasFlexEnabled = in_array( 'ap-flex', $flexClasses, true );
	if ( '' === $layoutType && $hasFlexEnabled ) {
		$layoutClass = 'is-layout-flex';
	}

	$wrapperBaseClasses = array_merge( [ 'wp-block-group', $layoutClass ], $flexClasses );
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, $wrapperBaseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</{{ $tag }}>
