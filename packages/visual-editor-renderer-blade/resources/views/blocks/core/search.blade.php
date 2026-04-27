@php
	$label       = (string) ( $attributes['label'] ?? 'Search' );
	$showLabel   = ! isset( $attributes['showLabel'] ) || ! empty( $attributes['showLabel'] );
	$placeholder = (string) ( $attributes['placeholder'] ?? '' );
	$buttonText  = (string) ( $attributes['buttonText'] ?? 'Search' );
	$useIcon     = ! empty( $attributes['buttonUseIcon'] );
	$queryName   = (string) ( $attributes['query']['name'] ?? 's' );

	$ariaLabel = '' !== trim( $buttonText )
		? $buttonText
		: ( '' !== trim( $label ) ? $label : 'Search' );

	$inputId = sprintf( 'wp-block-search-input-%s', substr( md5( $blockName . serialize( $attributes ) ), 0, 8 ) );

	$classes = [ 'wp-block-search' ];

	if ( ! $showLabel ) {
		$classes[] = 'wp-block-search__button-inside';
	}

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}

	$buttonClasses = [ 'wp-block-search__button' ];

	if ( $useIcon ) {
		$buttonClasses[] = 'has-icon';
	}

	$inputPlaceholder = '' !== $placeholder
		? sprintf( ' placeholder="%s"', e( $placeholder ) )
		: '';
@endphp
<form role="search" method="get" action="/" class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( $showLabel )
		<label class="wp-block-search__label" for="{{ $inputId }}">{{ $label }}</label>
	@endif
	<div class="wp-block-search__inside-wrapper">
		<input id="{{ $inputId }}" type="search" class="wp-block-search__input" name="{{ $queryName }}"{!! $inputPlaceholder !!}/>
		@if ( $useIcon )
<button type="submit" class="{{ implode( ' ', $buttonClasses ) }}" aria-label="{{ $ariaLabel }}"><svg class="wp-block-search__button-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3 1 1.1 3.4-3c1 .9 2.2 1.4 3.6 1.4 3 0 5.5-2.5 5.5-5.5C19 8.5 16.5 6 13.5 6zm0 9.5c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"></path></svg></button>
		@else
			<button type="submit" class="{{ implode( ' ', $buttonClasses ) }}">{{ $buttonText }}</button>
		@endif
	</div>
</form>
