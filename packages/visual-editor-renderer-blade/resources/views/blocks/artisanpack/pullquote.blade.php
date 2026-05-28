{{-- artisanpack/pullquote — delegates to core/pullquote. --}}
@include('visual-editor-renderer-blade::blocks.core.pullquote', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
