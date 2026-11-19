@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// Standalone fallback for a slide rendered outside its carousel. The
	// carousel renders its slides itself so it can number them (#1127).
	$ariaLabel = trim( (string) ( $attributes['ariaLabel'] ?? '' ) );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-carousel-slide', 'ap-carousel-slide' ] ) !!} role="group" aria-roledescription="{{ __( 'slide' ) }}"@if ( '' !== $ariaLabel ) aria-label="{{ $ariaLabel }}"@endif>
	{!! $innerBlocksHtml !!}
</div>
