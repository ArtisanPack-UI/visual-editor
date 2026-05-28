{{-- artisanpack/details — delegates to core/details. --}}
@include('visual-editor-renderer-blade::blocks.core.details', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
