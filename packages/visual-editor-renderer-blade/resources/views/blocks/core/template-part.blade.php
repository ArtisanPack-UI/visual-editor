@php
	$slug  = isset( $attributes['slug'] ) && is_string( $attributes['slug'] ) ? $attributes['slug'] : '';
	$theme = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? $attributes['theme'] : '';
	$tag   = isset( $attributes['tagName'] ) && in_array( $attributes['tagName'], [ 'div', 'header', 'footer', 'aside', 'section', 'main', 'nav' ], true )
		? $attributes['tagName']
		: 'div';

	$resolutionError = isset( $attributes['_resolutionError'] ) && is_string( $attributes['_resolutionError'] )
		? $attributes['_resolutionError']
		: null;

	$classes = [ 'wp-block-template-part' ];

	if ( '' !== $slug ) {
		$classes[] = 'wp-block-template-part--' . preg_replace( '/[^a-z0-9_-]/i', '-', $slug );
	}

	if ( ! empty( $attributes['className'] ) && is_string( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$inDev = function_exists( 'app' ) ? ! app()->environment( 'production' ) : false;
@endphp
<{{ $tag }} class="{{ implode( ' ', $classes ) }}" data-ve-template-part="{{ $slug }}"@if( '' !== $theme ) data-ve-theme="{{ $theme }}"@endif>
@if( null !== $resolutionError )
@if( $inDev )
<!-- visual-editor: template part "{{ $slug }}" failed to resolve ({{ $resolutionError }}) -->
@endif
@else
{!! $innerBlocksHtml !!}
@endif
</{{ $tag }}>
