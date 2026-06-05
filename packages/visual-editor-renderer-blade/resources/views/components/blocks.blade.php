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
@isset( $statesCss )
@if( '' !== $statesCss )
{!! $statesCss !!}
@endif
@endisset
{!! $html !!}
