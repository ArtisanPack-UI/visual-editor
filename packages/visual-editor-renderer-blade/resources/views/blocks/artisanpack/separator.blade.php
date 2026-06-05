{{-- artisanpack/separator — delegates to core/separator. --}}
@include('visual-editor-renderer-blade::blocks.core.separator', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
