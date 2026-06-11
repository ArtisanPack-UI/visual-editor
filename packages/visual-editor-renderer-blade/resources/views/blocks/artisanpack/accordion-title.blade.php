@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
{{-- Fallback wrapper when rendered outside an accordion parent. The parent
     accordion partial walks innerBlocks directly and emits the full
     toggle wiring (role, aria-controls, id, icon) using the parent's
     panelId, so this partial just outputs its content. --}}
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-accordion__title' ] ) !!}>
	{!! $innerBlocksHtml !!}
</div>
