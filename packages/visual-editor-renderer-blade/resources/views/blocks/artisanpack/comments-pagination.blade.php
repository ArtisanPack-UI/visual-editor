@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// F-3 — always a flex layout (block.json disallows switching): the
	// layout pair, a `wp-container-*` scope for its justification, wrap
	// and Block spacing, and WordPress's layout modifier classes.
	$baseClasses = array_merge(
		[ 'wp-block-comments-pagination' ],
		LayoutSupport::pair( 'comments-pagination', 'is-layout-flex' ),
		LayoutSupport::containerClasses( $attributes, 'comments-pagination', 'is-layout-flex' ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ __( 'Comments pagination' ) }}">
	{!! $innerBlocksHtml !!}
</nav>
