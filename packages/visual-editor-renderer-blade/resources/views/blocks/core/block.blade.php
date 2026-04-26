@php
	$ref = isset( $attributes['ref'] ) ? $attributes['ref'] : null;
	$trimmedRef = is_string( $ref ) ? trim( $ref ) : $ref;
	$refString = is_int( $ref ) || ( is_string( $trimmedRef ) && '' !== $trimmedRef ) ? (string) $trimmedRef : '';

	$resolutionError = isset( $attributes['_resolutionError'] ) && is_string( $attributes['_resolutionError'] )
		? $attributes['_resolutionError']
		: null;

	$classes = [ 'wp-block-block' ];

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$inDev = function_exists( 'app' ) ? ! app()->environment( 'production' ) : false;
@endphp
<div class="{{ implode( ' ', $classes ) }}"@if( '' !== $refString ) data-ve-pattern-ref="{{ $refString }}"@endif>
@if( null !== $resolutionError )
@if( $inDev )
<!-- visual-editor: synced pattern{{ '' !== $refString ? ' "' . $refString . '"' : '' }} failed to resolve ({{ $resolutionError }}) -->
@endif
@else
{!! $innerBlocksHtml !!}
@endif
</div>
