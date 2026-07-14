<div
	{{ $attributes->merge(['class' => 'ap-visual-editor']) }}
	data-ap-visual-editor
	data-resource="{{ $resource }}"
	data-id="{{ $modelId }}"
	data-api-base="{{ $apiBase }}"
	@isset($initialTitle) data-title="{{ $initialTitle }}" @endisset
	@isset($initialSlug) data-slug="{{ $initialSlug }}" @endisset
	@isset($initialStatus) data-status="{{ $initialStatus }}" @endisset
	@isset($initialExcerpt) data-excerpt="{{ $initialExcerpt }}" @endisset
	@isset($initialAuthorId) data-author-id="{{ $initialAuthorId }}" @endisset
	@isset($initialCommentsOpen) data-comments-open="{{ $initialCommentsOpen ? 'true' : 'false' }}" @endisset
	@isset($initialFeaturedImage) data-featured-image="{{ json_encode( $initialFeaturedImage ) }}" @endisset
	@isset($authorOptions) data-author-options="{{ json_encode( $authorOptions ) }}" @endisset
	@isset($supports) data-supports="{{ json_encode( $supports ) }}" @endisset
	@isset($previewUrl) data-preview-url="{{ $previewUrl }}" @endisset
	data-content-types="{{ json_encode( $contentTypes, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) }}"
	{{-- #617 — merged breakpoint registry (config + theme.json +
	     defaults) so the viewport switcher's registry hydrates with
	     host-configured `label` / `previewWidthPx` overrides. --}}
	data-breakpoints="{{ json_encode( $breakpoints ) }}"
></div>
