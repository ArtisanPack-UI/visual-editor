@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$summary     = (string) ( $attributes['summary'] ?? '' );
	$showContent = ! empty( $attributes['showContent'] );

	$openAttr = $showContent ? ' open' : '';
@endphp
<details{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-details' ] ) !!}{!! $openAttr !!}>
	<summary>{!! $summary !!}</summary>
	{!! $innerBlocksHtml !!}
</details>
