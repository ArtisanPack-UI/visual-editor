@isset( $globalStylesCss )
@if( null !== $globalStylesCss && '' !== $globalStylesCss )
<style data-ve-global-styles>{!! $globalStylesCss !!}</style>
@endif
@endisset
{!! $html !!}
