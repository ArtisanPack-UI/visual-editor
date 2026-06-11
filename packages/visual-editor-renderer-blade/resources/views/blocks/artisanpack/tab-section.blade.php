@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$tabIdRaw = isset( $attributes['tabId'] ) ? (string) $attributes['tabId'] : '';
	// Slugify to a valid HTML id (lowercase ASCII + hyphens) so any
	// special chars in the persisted value can't break the surrounding
	// attribute markup or produce an unreachable ARIA target. Mirrors
	// the sanitizeTabId helper in tabs.blade.php / React / Vue
	// renderers — kept here for the standalone fallback when a
	// tab-section is rendered outside its tabs parent.
	$tabIdSlug  = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $tabIdRaw ) ) ?? '';
	$tabIdSlug  = trim( $tabIdSlug, '-' );
	// `block.json` does not declare an `id` support, so
	// BlockSupports::wrapperAttrs cannot produce an `id` attribute —
	// emitting our own here can't collide.

	$idAttr        = '' === $tabIdSlug ? '' : ' id="tabs-panel-' . $tabIdSlug . '"';
	$labelledByAttr = '' === $tabIdSlug ? '' : ' aria-labelledby="tabs-tab-' . $tabIdSlug . '"';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-tab-section' ] ) !!}{!! $idAttr !!} role="tabpanel"{!! $labelledByAttr !!}>
	{!! $innerBlocksHtml !!}
</div>
