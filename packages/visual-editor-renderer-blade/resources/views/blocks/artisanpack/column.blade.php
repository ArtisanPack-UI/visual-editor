{{-- artisanpack/column — delegates to core/column. --}}
@include('visual-editor-renderer-blade::blocks.core.column', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
