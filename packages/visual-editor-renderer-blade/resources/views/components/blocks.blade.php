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
@isset( $animationsCss )
@if( '' !== $animationsCss )
{!! $animationsCss !!}
@endif
@endisset
@isset( $animationsNoscript )
@if( '' !== $animationsNoscript )
{!! $animationsNoscript !!}
@endif
@endisset
@isset( $gradientBordersCss )
@if( '' !== $gradientBordersCss )
{!! $gradientBordersCss !!}
@endif
@endisset
{!! $html !!}
@isset( $animationsRuntimeNeeded )
@if( $animationsRuntimeNeeded )
@include('visual-editor-renderer-blade::partials.animations-runtime')
@endif
@endisset
