{{-- H7 (#432) deny-by-default gate page.

	Rendered by {@see DenyByDefaultGate}, the package-default binding
	for `SiteEditorAccessGate`. Reached when a consuming app has
	installed the visual-editor package but has not yet bound an
	access gate of its own — the package fails closed so the editor
	cannot be exposed by accident.

	Inline styles are deliberate: the SPA's CSS chunks aren't loaded
	on this page (the gate is the alternative to mounting the SPA),
	so the page must stand on its own without Vite's site-editor
	bundle. --}}
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ __( 'Site editor not configured — ArtisanPack Visual Editor' ) }}</title>
	<style>
		:root {
			color-scheme: light dark;
		}

		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem;
			background: #f5f5f7;
			color: #1d1d1f;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			line-height: 1.5;
		}

		.ap-deny-gate {
			max-width: 36rem;
			background: #fff;
			border-radius: 0.75rem;
			padding: 2.5rem;
			box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.05 ), 0 8px 24px rgba( 0, 0, 0, 0.08 );
		}

		.ap-deny-gate__eyebrow {
			text-transform: uppercase;
			letter-spacing: 0.08em;
			font-size: 0.75rem;
			font-weight: 600;
			color: #86868b;
			margin: 0 0 0.75rem;
		}

		.ap-deny-gate__title {
			margin: 0 0 1rem;
			font-size: 1.625rem;
			font-weight: 700;
			color: #1d1d1f;
		}

		.ap-deny-gate__lede {
			margin: 0 0 1.25rem;
			font-size: 1rem;
			color: #1d1d1f;
		}

		.ap-deny-gate__lede code {
			font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
			background: #f5f5f7;
			border-radius: 0.25rem;
			padding: 0.1rem 0.35rem;
			font-size: 0.95em;
		}

		.ap-deny-gate__footer {
			margin: 0;
			font-size: 0.875rem;
			color: #6e6e73;
		}

		.ap-deny-gate__footer code {
			font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
			background: #f5f5f7;
			border-radius: 0.25rem;
			padding: 0.1rem 0.35rem;
			font-size: 0.95em;
		}

		@media ( prefers-color-scheme: dark ) {
			body {
				background: #1d1d1f;
				color: #f5f5f7;
			}

			.ap-deny-gate {
				background: #2c2c2e;
				box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.4 ), 0 8px 24px rgba( 0, 0, 0, 0.6 );
			}

			.ap-deny-gate__title {
				color: #f5f5f7;
			}

			.ap-deny-gate__lede {
				color: #e5e5e7;
			}

			.ap-deny-gate__lede code,
			.ap-deny-gate__footer code {
				background: #1d1d1f;
				color: #f5f5f7;
			}

			.ap-deny-gate__footer {
				color: #a1a1a6;
			}
		}
	</style>
</head>
<body>
	<main class="ap-deny-gate" role="main" data-testid="ap-deny-gate">
		<p class="ap-deny-gate__eyebrow">{{ __( 'Visual Editor' ) }}</p>
		<h1 class="ap-deny-gate__title">
			{{ __( 'Site editor access has not been configured' ) }}
		</h1>
		<p class="ap-deny-gate__lede">
			{!! __( 'This application has not bound an access gate for the visual-editor site editor. The package ships with a fail-closed default so the editor cannot be exposed by accident.' ) !!}
		</p>
		<p class="ap-deny-gate__footer">
			{!! __( 'Application developers: bind an implementation of :contract in your service provider. See the access-gate docs that ship with the package for details.', [ 'contract' => '<code>ArtisanPackUI\\VisualEditor\\SiteEditor\\Gates\\SiteEditorAccessGate</code>' ] ) !!}
		</p>
	</main>
</body>
</html>
