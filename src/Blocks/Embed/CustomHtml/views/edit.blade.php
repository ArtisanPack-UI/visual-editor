@php
	$htmlContent = $content['content'] ?? '';
	$preview     = $content['preview'] ?? false;
	$sanitize    = $content['sanitize'] ?? true;
	$cssClass    = $content['cssClass'] ?? '';
@endphp

<div
	class="ve-block ve-block-custom-html ve-block-editing"
	x-data="{
		htmlCode: {{ Js::from( $htmlContent ) }},
		getBlockId() {
			return Alpine.store( 'selection' )?.focused;
		},
		updateContent() {
			const blockId = this.getBlockId();
			if ( blockId ) {
				Alpine.store( 'editor' ).updateBlock( blockId, { content: this.htmlCode } );
			}
		},
	}"
>
	@if ( ! $sanitize )
		<div class="ve-custom-html-warning flex items-center gap-2 rounded-t-lg bg-warning/10 border border-warning/30 px-3 py-2" role="alert">
			<svg class="w-4 h-4 text-warning shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
			</svg>
			<span class="text-xs text-warning">{{ __( 'visual-editor::ve.custom_html_unsanitized_warning' ) }}</span>
		</div>
	@endif

	@if ( $preview )
		<div class="ve-custom-html-preview rounded-lg border border-base-300 overflow-hidden {{ ! $sanitize ? 'rounded-t-none border-t-0' : '' }}">
			<div class="flex items-center justify-between bg-base-200 px-3 py-1 border-b border-base-300">
				<span class="text-xs font-medium text-base-content/60">{{ __( 'visual-editor::ve.custom_html_preview' ) }}</span>
			</div>
			<iframe
				srcdoc="{{ e( $htmlContent ) }}"
				sandbox="allow-scripts"
				class="ve-custom-html-iframe"
				title="{{ __( 'visual-editor::ve.custom_html_preview_title' ) }}"
				aria-label="{{ __( 'visual-editor::ve.custom_html_preview_title' ) }}"
				style="width: 100%; min-height: 150px; border: 0;"
				loading="lazy"
			></iframe>
		</div>
	@else
		<div class="ve-custom-html-editor rounded-lg border border-base-300 overflow-hidden {{ ! $sanitize ? 'rounded-t-none border-t-0' : '' }}">
			<div class="flex items-center justify-between bg-base-200 px-3 py-1 border-b border-base-300">
				<span class="text-xs font-medium text-base-content/60">HTML</span>
			</div>
			<textarea
				class="ve-custom-html-textarea w-full font-mono text-sm p-3 bg-base-100 min-h-[150px] resize-y focus:outline-none"
				aria-label="{{ __( 'visual-editor::ve.custom_html_editor_label' ) }}"
				placeholder="{{ __( 'visual-editor::ve.custom_html_placeholder' ) }}"
				spellcheck="false"
				x-model="htmlCode"
				x-on:input.debounce.500ms="updateContent()"
			>{{ $htmlContent }}</textarea>
		</div>
	@endif
</div>
