@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
{{--
	comment-template wrapper.

	The server-side CommentInliner clones this block's inner tree once
	per resolved comment and stamps each iteration with the per-comment
	`_resolved*` attributes via CommentResolver, then concatenates the
	rendered iterations into `$innerBlocksHtml`. The renderer just
	wraps the result in the `<ol class="wp-block-comment-template">`
	scaffold WordPress uses.
--}}
<ol{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comment-template' ] ) !!}>
	{!! $innerBlocksHtml !!}
</ol>
