@php
	$tag = match ( $listStyle ) {
		'bulleted' => 'ul',
		'numbered' => 'ol',
		default    => 'ul',
	};
	$listClass = 'plain' === $listStyle ? 'list-none' : '';
@endphp

@if ( ! empty( $items ) )
	<{{ $tag }} class="ve-block-toc-list {{ $listClass }}">
		@foreach ( $items as $item )
			@php $safeId = preg_replace( '/[^a-zA-Z0-9_-]/', '', $item['id'] ?? '' ); @endphp
			<li class="ve-block-toc-item">
				<a
					href="#{{ $safeId }}"
					class="ve-block-toc-link"
					@if ( $smoothScroll )
						onclick="event.preventDefault(); document.getElementById('{{ $safeId }}')?.scrollIntoView({ behavior: 'smooth' });"
					@endif
				>{{ $item['text'] }}</a>
				@if ( ! empty( $item['children'] ) )
					@include( 'visual-editor::livewire.blocks.partials.toc-list', [ 'items' => $item['children'], 'listStyle' => $listStyle, 'smoothScroll' => $smoothScroll ] )
				@endif
			</li>
		@endforeach
	</{{ $tag }}>
@endif
