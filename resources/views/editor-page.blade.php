<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ $content->title ?? __( 'Visual Editor' ) }}</title>
	@livewireStyles
</head>
<body class="antialiased">
	<livewire:visual-editor::editor :content="$content" />
	@livewireScripts
</body>
</html>
