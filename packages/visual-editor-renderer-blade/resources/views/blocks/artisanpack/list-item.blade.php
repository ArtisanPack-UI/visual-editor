{{-- artisanpack/list-item — delegates to core/list-item. --}}
@include('visual-editor-renderer-blade::blocks.core.list-item', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
