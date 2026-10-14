{{--
	core/query-title — alias of the artisanpack/query-title fork (#856).

	Theme files that haven't been opened and saved in the site editor keep
	WordPress's core/* names. QueryInliner stamps the same _resolved*
	attributes for both namespaces, so delegating to the fork partial keeps
	both names byte-identical.
--}}
@include('visual-editor-renderer-blade::blocks.artisanpack.query-title')
