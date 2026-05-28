{{-- artisanpack/spacer — delegates to core/spacer. --}}
@include('visual-editor-renderer-blade::blocks.core.spacer', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
