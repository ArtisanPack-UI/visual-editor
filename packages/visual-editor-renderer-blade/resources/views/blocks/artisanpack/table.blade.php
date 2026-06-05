{{-- artisanpack/table — delegates to core/table. --}}
@include('visual-editor-renderer-blade::blocks.core.table', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
