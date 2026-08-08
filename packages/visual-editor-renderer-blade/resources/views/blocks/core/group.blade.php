@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\FlexSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\PhotoGridSupport;

	$tag = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$layoutType = isset( $attributes['layout']['type'] ) && is_string( $attributes['layout']['type'] )
		? trim( $attributes['layout']['type'] )
		: '';

	$layoutClass = LayoutSupport::layoutClass( $attributes );

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

	$photoGridClasses = PhotoGridSupport::wrapperForBlock( $attributes );

	// #700 — the block-library CSS targets the per-block compound
	// (`wp-block-group-is-layout-constrained`), so emit it alongside the
	// shared modifier.
	$wrapperBaseClasses = array_merge(
		[ 'wp-block-group' ],
		LayoutSupport::pair( 'group', $layoutClass ),
		$flexClasses,
		$photoGridClasses
	);
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, $wrapperBaseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</{{ $tag }}>
