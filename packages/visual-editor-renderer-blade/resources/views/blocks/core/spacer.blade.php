@php
	$height = $attributes['height'] ?? '100px';

	if ( is_numeric( $height ) ) {
		$height = $height . 'px';
	}

	$style = sprintf( 'height: %s;', (string) $height );
@endphp
<div aria-hidden="true" class="wp-block-spacer" style="{{ $style }}"></div>
