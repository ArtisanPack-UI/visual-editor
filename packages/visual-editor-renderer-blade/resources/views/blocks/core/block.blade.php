@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$ref = isset( $attributes['ref'] ) ? $attributes['ref'] : null;
	$trimmedRef = is_string( $ref ) ? trim( $ref ) : $ref;
	$refString = is_int( $ref ) || ( is_string( $trimmedRef ) && '' !== $trimmedRef ) ? (string) $trimmedRef : '';

	$resolutionError = isset( $attributes['_resolutionError'] ) && is_string( $attributes['_resolutionError'] )
		? $attributes['_resolutionError']
		: null;

	$inDev = function_exists( 'app' ) ? ! app()->environment( 'production' ) : false;

	$dataRefAttr = '' !== $refString ? sprintf( ' data-ve-pattern-ref="%s"', e( $refString ) ) : '';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-block' ] ) !!}{!! $dataRefAttr !!}>
@if( null !== $resolutionError )
@if( $inDev )
<!-- visual-editor: synced pattern{{ '' !== $refString ? ' "' . $refString . '"' : '' }} failed to resolve ({{ $resolutionError }}) -->
@endif
@else
{!! $innerBlocksHtml !!}
@endif
</div>
