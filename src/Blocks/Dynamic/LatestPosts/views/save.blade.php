@php
	$anchor   = $content['anchor'] ?? null;
	$htmlId   = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-latest-posts';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<livewire:visual-editor.blocks.latest-posts-block-component
		:post-type="$content['postType'] ?? 'post'"
		:number-of-posts="$content['numberOfPosts'] ?? 5"
		:order-by="$content['orderBy'] ?? 'date'"
		:order="$content['order'] ?? 'desc'"
		:categories="$content['categories'] ?? []"
		:tags="$content['tags'] ?? []"
		:display-template="$content['displayTemplate'] ?? 'list'"
		:show-featured-image="$content['showFeaturedImage'] ?? true"
		:show-excerpt="$content['showExcerpt'] ?? true"
		:show-date="$content['showDate'] ?? true"
		:show-author="$content['showAuthor'] ?? false"
		:excerpt-length="$content['excerptLength'] ?? 25"
		:columns="$content['columns'] ?? ['mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1]"
		:offset="$content['offset'] ?? 0"
		:exclude-current-post="$content['excludeCurrentPost'] ?? false"
		:gap="$styles['gap'] ?? '1rem'"
		:image-aspect-ratio="$styles['imageAspectRatio'] ?? '16/9'"
	/>
</div>
