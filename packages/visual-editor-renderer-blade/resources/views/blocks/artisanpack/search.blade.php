{{-- artisanpack/search — delegates to core/search (carries the #338 button-icon a11y fix). --}}
@include('visual-editor-renderer-blade::blocks.core.search', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? '', 'blockName' => $blockName ?? 'artisanpack/search'])
