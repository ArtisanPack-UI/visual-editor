@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-columns' ];

	if ( ! empty( $attributes['isStackedOnMobile'] ) || ! isset( $attributes['isStackedOnMobile'] ) ) {
		$baseClasses[] = 'is-stacked-on-mobile';
	}

	if ( ! empty( $attributes['verticalAlignment'] ) ) {
		$baseClasses[] = 'are-vertically-aligned-' . $attributes['verticalAlignment'];
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	{!! $innerBlocksHtml !!}
</div>
