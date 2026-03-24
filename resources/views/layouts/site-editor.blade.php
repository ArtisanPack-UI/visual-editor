{{--
 * Site Editor Layout
 *
 * Full-page Blade layout used by site editor Livewire components.
 * Provides the HTML shell with required styles and scripts.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Layouts
 *
 * @since      1.0.0
 --}}

<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" data-theme="light">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>{{ $title ?? __( 'visual-editor::ve.site_editor' ) }}</title>

	@stack( 'styles' )
	@livewireStyles
	{{-- Force light theme — dark mode support will be added in a future release --}}
	<script>document.documentElement.setAttribute('data-theme','light');document.documentElement.classList.remove('dark');</script>
</head>
<body class="antialiased">
	{{ $slot }}

	@livewireScripts
	@stack( 'scripts' )
</body>
</html>
