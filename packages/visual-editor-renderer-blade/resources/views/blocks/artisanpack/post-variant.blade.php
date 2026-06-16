{{--
	artisanpack/post-variant (#591).

	The server-side QueryInliner strips post-variant blocks from the
	rendered tree before the renderer sees them — variants are
	template overrides, not blocks that emit markup. This view exists
	to satisfy the renderer parity check and to provide a safe
	pass-through if a variant ever reaches the renderer (e.g. a
	host-side render of an un-inlined tree).
--}}
@if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) )
	@foreach ( $block['innerBlocks'] as $innerBlock )
		@include('visual-editor-renderer-blade::render-block', [ 'block' => $innerBlock ])
	@endforeach
@endif
