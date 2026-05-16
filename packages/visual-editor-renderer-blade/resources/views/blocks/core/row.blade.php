@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-group', 'is-layout-flex', 'is-horizontal' ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
