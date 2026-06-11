@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-accordions' ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
