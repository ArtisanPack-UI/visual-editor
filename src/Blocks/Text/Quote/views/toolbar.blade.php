@php
	$showCitation = $content['showCitation'] ?? false;
@endphp

<x-ve-toolbar-group>
	<x-ve-toolbar-button
		:active="$showCitation"
		:tooltip="$showCitation ? __( 'visual-editor::ve.remove_citation' ) : __( 'visual-editor::ve.add_citation' )"
		icon="chat-bubble-bottom-center-text"
		x-on:click="$store.editor.updateBlock(
			$store.selection.focused,
			{ showCitation: {{ $showCitation ? 'false' : 'true' }} }
		)"
	/>
</x-ve-toolbar-group>
