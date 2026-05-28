{{-- artisanpack/buttons — delegates to core/buttons. --}}
@include('visual-editor-renderer-blade::blocks.core.buttons', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
