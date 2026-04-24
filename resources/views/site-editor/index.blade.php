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
	<div
		id="ap-visual-editor-site-editor"
		data-ap-site-editor
		data-route-base="/visual-editor/site"
		data-post-editor-url="{{ route('visual-editor.editor') }}"
		data-api-base="/visual-editor/api"
		data-theme="{{ config('artisanpack.visual-editor.global_styles.theme', 'default') }}"
	></div>
</body>
</html>
