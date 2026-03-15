@php
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-search';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<livewire:visual-editor.blocks.search-block-component
		:placeholder="$content['placeholder'] ?? __( 'visual-editor::ve.search_placeholder' )"
		:button-text="$content['buttonText'] ?? __( 'visual-editor::ve.search' )"
		:button-position="$content['buttonPosition'] ?? 'outside'"
		:button-icon="$content['buttonIcon'] ?? 'magnifying-glass'"
		:show-label="$content['showLabel'] ?? true"
		:label="$content['label'] ?? __( 'visual-editor::ve.search' )"
		:results-per-page="$content['resultsPerPage'] ?? 10"
		:search-scope="$content['searchScope'] ?? 'all'"
		:display-style="$content['displayStyle'] ?? 'inline'"
	/>
</div>
