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
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">

	<title>{{ $title ?? __( 'visual-editor::ve.site_editor' ) }}</title>

	@stack( 'styles' )
	@livewireStyles
</head>
<body class="antialiased">
	{{ $slot }}

	@livewireScripts
	@stack( 'scripts' )
</body>
</html>
