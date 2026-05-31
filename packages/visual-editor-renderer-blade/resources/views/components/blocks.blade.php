@isset( $globalStylesCss )
@if( null !== $globalStylesCss && '' !== $globalStylesCss )
<style data-ve-global-styles>{!! $globalStylesCss !!}</style>
@endif
@endisset
@isset( $responsiveCss )
@if( '' !== $responsiveCss )
{!! $responsiveCss !!}
@endif
@endisset
{!! $html !!}
