@php
	$summary     = (string) ( $attributes['summary'] ?? '' );
	$showContent = ! empty( $attributes['showContent'] );

	$classes = [ 'wp-block-details' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$openAttr = $showContent ? ' open' : '';
@endphp
<details class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}"{!! $openAttr !!}>
	<summary>{!! $summary !!}</summary>
	{!! $innerBlocksHtml !!}
</details>
