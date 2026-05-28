{{-- artisanpack/row — delegates to core/row (variation of core/group upstream). --}}
@include('visual-editor-renderer-blade::blocks.core.row', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
