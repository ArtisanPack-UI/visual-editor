{{--
	artisanpack/post-author — Phase I5 entity fork (#413).

	Delegates to the core/post-author partial so the forked block renders
	byte-identical markup. The forked-block cutover keeps core/post-author
	registered in the editor, and the PostResolver / SiteMetaResolver stamp
	the same _resolved* attributes for both namespaces, so a one-line
	@include keeps the two renderers from drifting.
--}}
@include('visual-editor-renderer-blade::blocks.core.post-author')
