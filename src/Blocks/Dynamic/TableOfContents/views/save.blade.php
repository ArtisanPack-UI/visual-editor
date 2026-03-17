@php
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-table-of-contents';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<livewire:visual-editor.blocks.table-of-contents-block-component
		:heading-levels="$content['headingLevels'] ?? [2, 3]"
		:list-style="$content['listStyle'] ?? 'numbered'"
		:hierarchical="$content['hierarchical'] ?? true"
		:max-depth="$content['maxDepth'] ?? 3"
		:title="$content['title'] ?? __( 'visual-editor::ve.table_of_contents' )"
		:collapsible="$content['collapsible'] ?? false"
		:smooth-scroll="$content['smoothScroll'] ?? true"
	/>
</div>
