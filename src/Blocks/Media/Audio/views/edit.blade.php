@php
	$url     = $content['url'] ?? '';
	$caption = $content['caption'] ?? '';
	$preload = $styles['preload'] ?? 'metadata';

	$classes = 've-block ve-block-audio ve-block-editing';
@endphp

<figure class="{{ $classes }}">
	@if ( $url )
		<audio controls preload="{{ $preload }}">
			<source src="{{ $url }}">
		</audio>
	@else
		<div class="ve-audio-placeholder" data-placeholder="{{ __( 'visual-editor::ve.audio_url' ) }}">
			<span>{{ __( 'visual-editor::ve.audio_url' ) }}</span>
		</div>
	@endif
	<figcaption
		class="ve-audio-caption"
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.caption' ) }}"
	>{!! $caption !!}</figcaption>
</figure>
