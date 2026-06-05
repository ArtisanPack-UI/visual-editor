@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$slug  = isset( $attributes['slug'] ) && is_string( $attributes['slug'] ) ? $attributes['slug'] : '';
	$theme = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? $attributes['theme'] : '';
	$tag   = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'header', 'footer', 'aside', 'section', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$resolutionError = isset( $attributes['_resolutionError'] ) && is_string( $attributes['_resolutionError'] )
		? $attributes['_resolutionError']
		: null;

	$baseClasses = [ 'wp-block-template-part' ];

	if ( '' !== $slug ) {
		$baseClasses[] = 'wp-block-template-part--' . preg_replace( '/[^a-z0-9_-]/i', '-', $slug );
	}

	$inDev = function_exists( 'app' ) ? ! app()->environment( 'production' ) : false;

	$dataAttrs = sprintf( ' data-ve-template-part="%s"', e( $slug ) );

	if ( '' !== $theme ) {
		$dataAttrs .= sprintf( ' data-ve-theme="%s"', e( $theme ) );
	}
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}{!! $dataAttrs !!}>
@if( null !== $resolutionError )
@if( $inDev )
<!-- visual-editor: template part "{{ $slug }}" failed to resolve ({{ $resolutionError }}) -->
@endif
@else
{!! $innerBlocksHtml !!}
@endif
</{{ $tag }}>
