{{--
	artisanpack/single-content — single-entry container (#501).

	Reads `_resolvedHasPost` stamped by `QueryInliner` after resolving the
	chosen post (or falling back to the host post) through the visual
	editor's `QueryResolverContract`. When no post resolves the wrapper
	renders nothing — matches the original block contract where empty
	markup is preferred to a dangling placeholder. The inner-block tree
	is already re-stamped against the resolved post upstream, so this
	template only owns the wrapper.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$hasPost = ! empty( $attributes['_resolvedHasPost'] );
@endphp
@if ( $hasPost )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-single-content' ] ) !!}>{!! $innerBlocksHtml !!}</div>
@endif
