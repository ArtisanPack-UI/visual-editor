{{-- artisanpack/code — delegates to core/code. --}}
@include('visual-editor-renderer-blade::blocks.core.code', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
