{{-- artisanpack/stack — delegates to core/stack (variation of core/group upstream). --}}
@include('visual-editor-renderer-blade::blocks.core.stack', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
