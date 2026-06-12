{{--
	artisanpack/next-post — adjacent-post container (#499).

	Reads the `_resolvedHasAdjacent` flag PostResolver stamps after
	resolving the next adjacent post. When the host post has no neighbor
	the wrapper renders nothing — matches the original block contract
	where empty markup is preferred to a dangling placeholder. The
	inner-block tree itself is already resolved against the adjacent
	post upstream, so this template only owns the wrapper.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$hasAdjacent = ! empty( $attributes['_resolvedHasAdjacent'] );
@endphp
@if ( $hasAdjacent )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-next-post', 'navigation-post' ] ) !!}>{!! $innerBlocksHtml !!}</div>
@endif
