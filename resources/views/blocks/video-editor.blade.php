@php
	$url     = $content['url'] ?? '';
	$caption = $content['caption'] ?? '';
	$poster  = $content['poster'] ?? '';

	$classes = 've-block ve-block-video ve-block-editing';
@endphp

<figure class="{{ $classes }}">
	@if ( $url )
		<div class="ve-block-video__preview" style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; background: #000;">
			<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff;">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48">
					<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
				</svg>
			</div>
		</div>
	@else
		<div class="ve-block-video__placeholder">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="48" height="48">
				<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
			</svg>
			<p>{{ __( 'visual-editor::ve.block_video_placeholder' ) }}</p>
		</div>
	@endif
	<figcaption
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.caption_placeholder' ) }}"
	>{!! $caption !!}</figcaption>
</figure>
