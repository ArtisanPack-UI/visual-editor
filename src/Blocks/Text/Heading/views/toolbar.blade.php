@php
	$currentLevel = $content['level'] ?? 'h2';
	$levels       = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];
@endphp

<x-ve-toolbar-group>
	@foreach ( $levels as $level )
		<x-ve-toolbar-button
			:active="$currentLevel === $level"
			:label="strtoupper( $level )"
			x-on:click="$store.editor.updateBlock(
				$store.selection.focused,
				'content',
				'level',
				'{{ $level }}'
			)"
		>
			{{ strtoupper( $level ) }}
		</x-ve-toolbar-button>
	@endforeach
</x-ve-toolbar-group>
