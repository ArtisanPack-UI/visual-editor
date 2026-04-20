@php
	$label       = (string) ( $attributes['label'] ?? 'Search' );
	$showLabel   = ! isset( $attributes['showLabel'] ) || ! empty( $attributes['showLabel'] );
	$placeholder = (string) ( $attributes['placeholder'] ?? '' );
	$buttonText  = (string) ( $attributes['buttonText'] ?? 'Search' );
	$buttonUse   = (string) ( $attributes['buttonUseIcon'] ?? '' );
	$queryName   = (string) ( $attributes['query']['name'] ?? 's' );
	$buttonLabel = '' !== $buttonUse ? $buttonUse : $buttonText;

	$classes = [ 'wp-block-search' ];

	if ( ! $showLabel ) {
		$classes[] = 'wp-block-search__button-inside';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$inputPlaceholder = '' !== $placeholder
		? sprintf( ' placeholder="%s"', e( $placeholder ) )
		: '';
@endphp
<form role="search" method="get" action="/" class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( $showLabel )
		<label class="wp-block-search__label" for="wp-block-search-input">{{ $label }}</label>
	@endif
	<div class="wp-block-search__inside-wrapper">
		<input id="wp-block-search-input" type="search" class="wp-block-search__input" name="{{ $queryName }}"{!! $inputPlaceholder !!}/>
		<button type="submit" class="wp-block-search__button">{{ $buttonLabel }}</button>
	</div>
</form>
