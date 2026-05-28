{{-- artisanpack/audio — delegates to core/audio. --}}
@include('visual-editor-renderer-blade::blocks.core.audio', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
