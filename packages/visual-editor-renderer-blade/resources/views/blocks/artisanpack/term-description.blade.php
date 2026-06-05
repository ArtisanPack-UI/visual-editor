{{--
	artisanpack/term-description — Phase I-Block-Fork (#520).

	Delegates to the core/term-description partial so the forked block renders
	byte-identical markup. The forked-block cutover keeps core/term-description
	registered in the editor, and the PostResolver stamps the same
	_resolved* attributes for both namespaces, so a one-line @include keeps
	the two renderers from drifting.
--}}
@include('visual-editor-renderer-blade::blocks.core.term-description')
