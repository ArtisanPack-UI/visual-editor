@if( $emitBlockLibrary )
<link rel="stylesheet" href="{{ $styleHref }}" data-ve-block-library>
<link rel="stylesheet" href="{{ $themeHref }}" data-ve-block-library-theme>
@endif
@if( '' !== $themeTokensCss )
<style data-ve-theme-tokens>{!! $themeTokensCss !!}</style>
@endif
