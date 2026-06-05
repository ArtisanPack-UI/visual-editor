{{-- artisanpack/group — delegates to core/group. --}}
@include('visual-editor-renderer-blade::blocks.core.group', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
