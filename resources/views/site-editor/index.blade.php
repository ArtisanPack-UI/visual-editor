<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	{{-- CSRF token for the site-editor's mutating REST requests; the
	     React client reads this meta tag and forwards it as the
	     `X-CSRF-TOKEN` header on POST/PUT/DELETE calls. --}}
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>ArtisanPack Visual Editor — Site Editor</title>
	@viteReactRefresh
	{{-- D1 (#368). Site-editor shell entry. Mounts the SPA into the
	     `[data-ap-site-editor]` element below; the React app reads the
	     current URL pathname under `data-route-base` to resolve the
	     active section and entity. --}}
	@vite(['resources/js/visual-editor/site-editor/main.tsx'])
</head>
<body class="ap-visual-editor-site-editor-body">
	{{-- #446. `data-exit-url` / `data-exit-label` drive the top-bar
	     exit link. The dev app points it back at the post editor; CMS
	     hosts override this blade to point at their own admin
	     dashboard (and the link is optional — omit `data-exit-url` and
	     no link renders). --}}
	<div
		id="ap-visual-editor-site-editor"
		data-ap-site-editor
		data-route-base="/visual-editor/site"
		data-exit-url="{{ route('visual-editor.editor') }}"
		data-exit-label="{{ __('← Post editor') }}"
		data-api-base="/visual-editor/api"
		data-theme="{{ config('artisanpack.visual-editor.global_styles.theme', 'default') }}"
		{{-- #617 — the merged breakpoint registry (config +
		     theme.json + defaults). The React shell hydrates the
		     viewport switcher's registry from this so host-configured
		     `label` / `previewWidthPx` overrides reach the UI. --}}
		data-breakpoints="{{ json_encode( app( \ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry::class )->toArray() ) }}"
	></div>
</body>
</html>
