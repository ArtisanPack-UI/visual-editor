@php
	$wrapperClasses = [ 'wp-site-blocks' ];

	if ( null !== $matchedSlug ) {
		$wrapperClasses[] = 'wp-site-blocks--' . preg_replace( '/[^a-z0-9_-]/i', '-', $matchedSlug );
	}
@endphp
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
@isset( $boxShadowsCss )
@if( '' !== $boxShadowsCss )
{!! $boxShadowsCss !!}
@endif
@endisset
@isset( $positionCss )
@if( '' !== $positionCss )
{!! $positionCss !!}
@endif
@endisset
<div class="{{ implode( ' ', $wrapperClasses ) }}" data-ve-template="{{ $slug }}"@if( null !== $matchedSlug && $matchedSlug !== $slug ) data-ve-matched-template="{{ $matchedSlug }}"@endif>
@if( null !== $resolutionError )
@if( $inDev )
<!-- visual-editor: template "{{ $slug }}" failed to resolve ({{ $resolutionError }}); fallback chain tried: {{ implode( ', ', $fallbackChain ) }} -->
@endif
@else
{!! $html !!}
@endif
</div>
@isset( $animationsRuntimeNeeded )
@if( $animationsRuntimeNeeded )
@include('visual-editor-renderer-blade::partials.animations-runtime')
@endif
@endisset
