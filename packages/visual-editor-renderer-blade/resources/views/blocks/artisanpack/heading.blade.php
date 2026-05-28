{{--
    artisanpack/heading — delegates to the upstream core/heading partial.

    The saved markup for artisanpack/heading is byte-equivalent to upstream
    core/heading, so we share one Blade partial for both namespaces. If
    the artisanpack fork ever diverges visually, lift the body of
    core/heading.blade.php into this file and edit independently.

    Phase I1 content cluster (issue #409).
--}}
@include('visual-editor-renderer-blade::blocks.core.heading', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
