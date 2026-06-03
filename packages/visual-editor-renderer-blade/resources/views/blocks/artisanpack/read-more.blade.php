{{--
	artisanpack/read-more — Phase I-Block-Fork (#520).

	Delegates to the core/read-more partial so the forked block renders
	byte-identical markup. The forked-block cutover keeps core/read-more
	registered in the editor, and the PostResolver stamps the same
	_resolved* attributes for both namespaces, so a one-line @include keeps
	the two renderers from drifting.
--}}
@include('visual-editor-renderer-blade::blocks.core.read-more')
