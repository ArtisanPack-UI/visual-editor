{{-- artisanpack/columns — delegates to core/columns. --}}
@include('visual-editor-renderer-blade::blocks.core.columns', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
