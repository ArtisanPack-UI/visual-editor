<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ArtisanPack Visual Editor</title>
	@viteReactRefresh
	@vite(['resources/js/visual-editor/editor/main.tsx'])
</head>
<body>
	<div
		data-ap-visual-editor
		data-resource="{{ $resource }}"
		data-id="{{ $modelId }}"
		data-api-base="{{ $apiBase }}"
		data-content-types="{{ json_encode( $contentTypes ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) }}"
		{{-- #617 — merged breakpoint registry (config + theme.json +
		     defaults). Reached by the React shell so the viewport
		     switcher's registry respects host-configured
		     `label` / `previewWidthPx` overrides. --}}
		data-breakpoints="{{ json_encode( app( \ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry::class )->toArray() ) }}"
	></div>
</body>
</html>
