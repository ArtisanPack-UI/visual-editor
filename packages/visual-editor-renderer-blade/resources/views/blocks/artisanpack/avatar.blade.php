{{--
	artisanpack/avatar — author family fork (#518).

	Delegates to the core/avatar partial so the forked block renders
	byte-identical markup. The forked-block cutover keeps core/avatar
	registered in the editor, and the PostResolver stamps the same
	_resolved* attributes for both namespaces, so a one-line @include
	keeps the two renderers from drifting.
--}}
@include('visual-editor-renderer-blade::blocks.core.avatar')
