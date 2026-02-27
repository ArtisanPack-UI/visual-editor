@php
	$url                = $content['url'] ?? '';
	$filename           = $content['filename'] ?? '';
	$fileSize           = $content['fileSize'] ?? '';
	$showDownloadButton = $styles['showDownloadButton'] ?? true;
	$anchor             = $content['anchor'] ?? null;
	$htmlId             = $content['htmlId'] ?? null;
	$className          = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$classes = 've-block ve-block-file';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	<div class="ve-block-file__content">
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="24" height="24">
			<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
		</svg>
		<div class="ve-block-file__info">
			@if ( $filename )
				<span class="ve-block-file__name">{{ $filename }}</span>
			@endif
			@if ( $fileSize )
				<span class="ve-block-file__size">{{ $fileSize }}</span>
			@endif
		</div>
		@if ( $showDownloadButton && $url )
			<a href="{{ $url }}" class="ve-block-file__download" download>
				{{ __( 'visual-editor::ve.download' ) }}
			</a>
		@endif
	</div>
</div>
