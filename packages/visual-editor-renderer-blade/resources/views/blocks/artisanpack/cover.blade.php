{{-- artisanpack/cover — delegates to core/cover. --}}
@include('visual-editor-renderer-blade::blocks.core.cover', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
