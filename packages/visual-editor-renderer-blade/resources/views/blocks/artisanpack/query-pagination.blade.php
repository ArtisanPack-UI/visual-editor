@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
{{-- artisanpack/query-pagination — Phase I-Block-Fork query family (#521).
	Pagination wrapper around the next / numbers / previous children. --}}
<nav{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination' ] ) !!} aria-label="{{ __( 'Pagination' ) }}">
	{!! $innerBlocksHtml !!}
</nav>
