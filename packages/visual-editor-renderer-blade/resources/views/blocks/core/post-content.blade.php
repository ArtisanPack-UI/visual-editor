@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] ) ? $attributes['_resolvedContent'] : '';

	// #700 — the block stores a `layout` attribute like every other
	// layout-supporting block, but the wrapper never surfaced it. Without
	// `is-layout-constrained` here the content-size containment rules
	// `ThemeJsonTokensCompiler` emits for `.wp-block-post-content` can
	// never match.
	$baseClasses = array_merge(
		[ 'entry-content', 'wp-block-post-content' ],
		LayoutSupport::wrapperForBlock( $attributes, 'post-content' )
	);
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>{!! $content !!}</div>
