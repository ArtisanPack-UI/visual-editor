{{-- artisanpack/media-text — delegates to core/media-text. --}}
@include('visual-editor-renderer-blade::blocks.core.media-text', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? '', 'innerBlocksHtml' => $innerBlocksHtml ?? ''])
