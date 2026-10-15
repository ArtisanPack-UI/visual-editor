@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$content = isset( $attributes['_resolvedContent'] ) && is_string( $attributes['_resolvedContent'] ) ? $attributes['_resolvedContent'] : '';

	// #700 — the block stores a `layout` attribute like every other
	// layout-supporting block, but the wrapper never surfaced it. Without
	// `is-layout-constrained` here the content-size containment rules
	// `ThemeJsonTokensCompiler` emits for `.wp-block-post-content` can
	// never match. #871 — its own content / wide size and justification
	// ride on a hashed `wp-container-*` scope.
	$layoutClass = LayoutSupport::layoutClass( $attributes );
	$baseClasses = array_merge(
		[ 'entry-content', 'wp-block-post-content' ],
		LayoutSupport::pair( 'post-content', $layoutClass ),
		LayoutSupport::containerClasses( $attributes, 'post-content', $layoutClass )
	);
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>{!! $content !!}</div>
