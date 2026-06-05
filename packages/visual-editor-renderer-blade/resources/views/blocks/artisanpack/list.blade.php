{{--
    artisanpack/list — delegates to the upstream core/list partial. See
    artisanpack/heading.blade.php for the rationale.
--}}
@include('visual-editor-renderer-blade::blocks.core.list', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
