@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$src      = (string) ( $attributes['src'] ?? '' );
	$caption  = (string) ( $attributes['caption'] ?? '' );
	$autoplay = ! empty( $attributes['autoplay'] );
	$loop     = ! empty( $attributes['loop'] );
	$preload  = isset( $attributes['preload'] ) ? (string) $attributes['preload'] : 'none';

	$audioAttrs = ' controls';
	$audioAttrs .= sprintf( ' src="%s"', e( $src ) );
	$audioAttrs .= sprintf( ' preload="%s"', e( $preload ) );

	if ( $autoplay ) {
		$audioAttrs .= ' autoplay';
	}

	if ( $loop ) {
		$audioAttrs .= ' loop';
	}
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-audio' ] ) !!}>
	@if ( '' !== $src )
		<audio{!! $audioAttrs !!}></audio>
	@endif
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
