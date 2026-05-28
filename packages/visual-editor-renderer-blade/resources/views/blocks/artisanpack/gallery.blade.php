{{-- artisanpack/gallery — delegates to core/gallery. --}}
@include('visual-editor-renderer-blade::blocks.core.gallery', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
