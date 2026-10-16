@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$summary     = (string) ( $attributes['summary'] ?? '' );
	$showContent = ! empty( $attributes['showContent'] );

	$openAttr = $showContent ? ' open' : '';

	// F-3 — details is a flow layout whose settings can't be edited
	// (`allowEditing: false`), so it carries the flow pair, a
	// `wp-container-*` scope when it sets its own Block spacing, and any
	// layout modifier classes, like every other layout block.
	$layoutClass = LayoutSupport::layoutClass( $attributes );
	$baseClasses = array_merge(
		[ 'wp-block-details' ],
		LayoutSupport::pair( 'details', $layoutClass ),
		LayoutSupport::containerClasses( $attributes, 'details', $layoutClass ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
<details{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}{!! $openAttr !!}>
	<summary>{!! $summary !!}</summary>
	{!! $innerBlocksHtml !!}
</details>
