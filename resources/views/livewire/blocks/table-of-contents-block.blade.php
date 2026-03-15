<div>
	<nav aria-label="{{ __( 'visual-editor::ve.table_of_contents' ) }}" class="ve-block ve-block-table-of-contents">
		@if ( $collapsible )
			<details open>
				<summary class="ve-block-toc-title">{{ $title ?: __( 'visual-editor::ve.table_of_contents' ) }}</summary>
				@include( 'visual-editor::livewire.blocks.partials.toc-list', [ 'items' => $tocItems, 'listStyle' => $listStyle, 'smoothScroll' => $smoothScroll ] )
			</details>
		@else
			@if ( $title )
				<h2 class="ve-block-toc-title">{{ $title }}</h2>
			@endif
			@include( 'visual-editor::livewire.blocks.partials.toc-list', [ 'items' => $tocItems, 'listStyle' => $listStyle, 'smoothScroll' => $smoothScroll ] )
		@endif
	</nav>
</div>
