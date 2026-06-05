{{-- artisanpack/preformatted — delegates to core/preformatted. --}}
@include('visual-editor-renderer-blade::blocks.core.preformatted', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
