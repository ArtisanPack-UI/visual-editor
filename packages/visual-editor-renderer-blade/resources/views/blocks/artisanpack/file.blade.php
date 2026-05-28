{{-- artisanpack/file — delegates to core/file. --}}
@include('visual-editor-renderer-blade::blocks.core.file', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
