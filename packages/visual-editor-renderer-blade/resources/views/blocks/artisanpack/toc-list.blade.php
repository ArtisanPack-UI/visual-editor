@php
	// Nested list partial for artisanpack/toc (#760). Recursively
	// renders the tree built by the parent partial.
	$listTag = $listTag ?? 'ul';
	$nodes   = $nodes ?? [];
@endphp
<{{ $listTag }} class="ap-toc__list">
	@foreach ( $nodes as $node )
		<li class="ap-toc__item">
			<a class="ap-toc__link" href="#{{ $node['anchor'] }}">{{ $node['text'] }}</a>
			@if ( ! empty( $node['children'] ) )
				@include( 'visual-editor-renderer-blade::blocks.artisanpack.toc-list', [
					'nodes'   => $node['children'],
					'listTag' => $listTag,
				] )
			@endif
		</li>
	@endforeach
</{{ $listTag }}>
