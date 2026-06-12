{{--
	artisanpack/post-types-search-results — parent wrapper (#502).

	Each child decides whether to emit anything based on the current
	`?post_type=` request, so the parent just owns the wrapper div.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-post-types-search-results' ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
