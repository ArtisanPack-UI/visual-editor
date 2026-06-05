{{-- artisanpack/video — delegates to core/video. --}}
@include('visual-editor-renderer-blade::blocks.core.video', ['attributes' => $attributes ?? [], 'innerContent' => $innerContent ?? ''])
