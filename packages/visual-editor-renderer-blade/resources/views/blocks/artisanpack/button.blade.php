{{-- artisanpack/button — delegates to core/button. --}}
@include('visual-editor-renderer-blade::blocks.core.button', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
