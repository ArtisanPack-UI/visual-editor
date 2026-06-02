@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comments-pagination' ] ) !!} aria-label="{{ __( 'Comments pagination' ) }}">
	{!! $innerBlocksHtml !!}
</nav>
