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
	></div>
</body>
</html>
