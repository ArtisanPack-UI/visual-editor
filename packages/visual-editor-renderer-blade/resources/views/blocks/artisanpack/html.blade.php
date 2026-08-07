{{-- artisanpack/html — delegates to core/html. --}}
@include('visual-editor-renderer-blade::blocks.core.html', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
