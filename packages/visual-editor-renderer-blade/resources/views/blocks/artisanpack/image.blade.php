{{-- artisanpack/image — delegates to core/image. --}}
@include('visual-editor-renderer-blade::blocks.core.image', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
