{{--
	artisanpack/post-navigation-link — Phase I-Block-Fork (#520).

	Delegates to the core/post-navigation-link partial so the forked block
	renders byte-identical markup. The forked-block cutover keeps
	core/post-navigation-link registered in the editor, and the PostResolver
	stamps the same _resolved* attributes for both namespaces, so a one-line
	@include keeps the two renderers from drifting.
--}}
@include('visual-editor-renderer-blade::blocks.core.post-navigation-link')
