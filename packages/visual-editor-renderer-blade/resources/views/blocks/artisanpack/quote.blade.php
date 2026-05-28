{{-- artisanpack/quote — delegates to core/quote. --}}
@include('visual-editor-renderer-blade::blocks.core.quote', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
