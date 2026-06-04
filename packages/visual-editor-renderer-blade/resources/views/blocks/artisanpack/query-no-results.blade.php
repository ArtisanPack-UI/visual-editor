@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
{{-- artisanpack/query-no-results — Phase I-Block-Fork query family (#521).
	QueryInliner drops the block when the surrounding artisanpack/query
	resolves to one or more posts; this partial only renders when the
	empty-state markup is intended to show. --}}
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-no-results' ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
