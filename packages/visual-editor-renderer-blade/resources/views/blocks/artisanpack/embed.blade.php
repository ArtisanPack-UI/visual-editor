{{-- artisanpack/embed — delegates to core/embed. --}}
@include('visual-editor-renderer-blade::blocks.core.embed', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
