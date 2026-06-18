{{--
	artisanpack/related-posts — related-by-taxonomy loop (#501).

	`QueryInliner` resolves N related posts for the host entry and clones
	the saved inner-block tree once per result with `_resolved*` stamps
	applied through `PostResolver`. This template renders the outer
	wrapper plus an inner row per iteration; when no results matched
	(`_resolvedItems` is zero) the wrapper renders nothing so the
	surrounding layout collapses cleanly.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawItems = $attributes['_resolvedItems'] ?? 0;
	$items    = is_numeric( $rawItems ) ? (int) $rawItems : 0;
@endphp
@if ( $items > 0 )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-related-posts' ] ) !!}>{!! $innerBlocksHtml !!}</div>
@endif
