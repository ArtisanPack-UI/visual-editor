@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$justify = (string) ( $attributes['layout']['justifyContent'] ?? '' );

	$baseClasses = [
		'wp-block-buttons',
		'is-layout-flex',
		'' !== $justify ? 'is-content-justification-' . $justify : 'is-content-justification-left',
	];
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
