@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// A slide inside a carousel carries the `_veCarouselSlide` token
	// BlockRenderer stamped on it (#1127). It prints the token in a
	// `data-ap-carousel-slide` marker, which the carousel counts and
	// removes, and as its aria-label unless the author set one; the
	// carousel swaps that for the slide's "n of N" label. A slide rendered
	// on its own has neither. `anchor` is not a slide support.
	$slideAttributes = $attributes;
	unset( $slideAttributes['anchor'] );

	$slideToken = (string) ( $attributes['_veCarouselSlide'] ?? '' );
	$ariaLabel  = trim( (string) ( $attributes['ariaLabel'] ?? '' ) );

	if ( '' === $ariaLabel ) {
		$ariaLabel = $slideToken;
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $slideAttributes, [ 'wp-block-artisanpack-carousel-slide', 'ap-carousel-slide' ] ) !!} role="group" aria-roledescription="{{ __( 'slide' ) }}"@if ( '' !== $slideToken ) data-ap-carousel-slide="{{ $slideToken }}"@endif @if ( '' !== $ariaLabel ) aria-label="{{ $ariaLabel }}"@endif>{!! $innerBlocksHtml !!}</div>
