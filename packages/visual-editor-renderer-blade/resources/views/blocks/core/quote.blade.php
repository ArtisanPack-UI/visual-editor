@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	$citation = (string) ( $attributes['citation'] ?? '' );

	// F-3 — quote is a flow layout whose settings can't be edited
	// (`allowEditing: false`), so it carries the flow pair, a
	// `wp-container-*` scope when it sets its own Block spacing, and any
	// layout modifier classes, like every other layout block.
	$layoutClass = LayoutSupport::layoutClass( $attributes );
	$baseClasses = array_merge(
		[ 'wp-block-quote' ],
		LayoutSupport::pair( 'quote', $layoutClass ),
		LayoutSupport::containerClasses( $attributes, 'quote', $layoutClass ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
<blockquote{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
	@if ( '' !== trim( $citation ) )
		<cite>{!! $citation !!}</cite>
	@endif
</blockquote>
