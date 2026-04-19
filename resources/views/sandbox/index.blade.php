<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ArtisanPack Visual Editor — Gutenberg Sandbox</title>
	@viteReactRefresh
	{{-- Temporary sandbox for M1 (#311). Proves @wordpress/* packages import cleanly
	     and lazy-load into a dedicated `gutenberg` chunk. Deleted once the real editor
	     shell ships (M3+); see docs/gutenberg-adoption.md. --}}
	@vite(['resources/js/visual-editor/sandbox/main.tsx'])
</head>
<body>
	<div id="ve-sandbox-root"></div>
</body>
</html>
