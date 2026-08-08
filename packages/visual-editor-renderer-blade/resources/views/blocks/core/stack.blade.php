@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// Renders `wp-block-group` markup, so the per-block layout compound
	// is keyed on `group` rather than `stack` (#700).
	$baseClasses = array_merge(
		[ 'wp-block-group' ],
		LayoutSupport::pair( 'group', 'is-layout-flex' ),
		[ 'is-vertical' ]
	);
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
