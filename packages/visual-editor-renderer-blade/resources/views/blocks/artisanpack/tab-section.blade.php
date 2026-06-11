@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tabId = isset( $attributes['tabId'] ) ? (string) $attributes['tabId'] : '';
	// Escape so quotes / brackets in the persisted slug can't break the
	// surrounding attribute.
	$tabIdSafe = htmlspecialchars( $tabId, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$idAttr        = '' === $tabId ? '' : ' id="tabs-panel-' . $tabIdSafe . '"';
	$labelledByAttr = '' === $tabId ? '' : ' aria-labelledby="tabs-tab-' . $tabIdSafe . '"';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-tab-section' ] ) !!}{!! $idAttr !!} role="tabpanel"{!! $labelledByAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
