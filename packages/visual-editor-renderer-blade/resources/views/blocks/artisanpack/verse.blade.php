{{-- artisanpack/verse — delegates to core/verse. --}}
@include('visual-editor-renderer-blade::blocks.core.verse', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
