{{-- H7 (#432) install gate.

	Rendered by the `/visual-editor/site/{path?}` route closure when
	cms-framework's SiteEditor module is not booted. Per plan 14 §2.1
	the site-editor surface is hard-coupled to cms-framework — without
	it the H6 controllers all 404, which would cascade through the SPA
	as a sea of error banners. This page short-circuits that, telling
	the user how to get unstuck and pointing them back at the post
	editor (which remains functional standalone).

	Inline styles are deliberate: the SPA's CSS chunks aren't loaded
	on this page (the gate is the alternative to mounting the SPA), so
	the page must stand on its own without Vite's site-editor bundle. --}}
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{{ __( 'Site editor unavailable — ArtisanPack Visual Editor' ) }}</title>
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

		.ap-install-gate {
			max-width: 36rem;
			background: #fff;
			border-radius: 0.75rem;
			padding: 2.5rem;
			box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.05 ), 0 8px 24px rgba( 0, 0, 0, 0.08 );
		}

		.ap-install-gate__eyebrow {
			text-transform: uppercase;
			letter-spacing: 0.08em;
			font-size: 0.75rem;
			font-weight: 600;
			color: #86868b;
			margin: 0 0 0.75rem;
		}

		.ap-install-gate__title {
			margin: 0 0 1rem;
			font-size: 1.625rem;
			font-weight: 700;
			color: #1d1d1f;
		}

		.ap-install-gate__lede {
			margin: 0 0 1.25rem;
			font-size: 1rem;
			color: #1d1d1f;
		}

		.ap-install-gate__lede code {
			font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
			background: #f5f5f7;
			border-radius: 0.25rem;
			padding: 0.1rem 0.35rem;
			font-size: 0.95em;
		}

		.ap-install-gate__command {
			background: #1d1d1f;
			color: #f5f5f7;
			border-radius: 0.5rem;
			padding: 1rem 1.25rem;
			font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
			font-size: 0.9rem;
			margin: 0 0 1.5rem;
			overflow-x: auto;
			white-space: pre;
		}

		.ap-install-gate__footer {
			margin: 0;
			font-size: 0.875rem;
			color: #6e6e73;
		}

		.ap-install-gate__footer a {
			color: #0066cc;
			text-decoration: none;
		}

		.ap-install-gate__footer a:hover,
		.ap-install-gate__footer a:focus {
			text-decoration: underline;
		}

		@media ( prefers-color-scheme: dark ) {
			body {
				background: #1d1d1f;
				color: #f5f5f7;
			}

			.ap-install-gate {
				background: #2c2c2e;
				box-shadow: 0 1px 2px rgba( 0, 0, 0, 0.4 ), 0 8px 24px rgba( 0, 0, 0, 0.6 );
			}

			.ap-install-gate__title {
				color: #f5f5f7;
			}

			.ap-install-gate__lede {
				color: #e5e5e7;
			}

			.ap-install-gate__lede code {
				background: #1d1d1f;
				color: #f5f5f7;
			}

			.ap-install-gate__command {
				background: #000;
				color: #f5f5f7;
			}

			.ap-install-gate__footer {
				color: #a1a1a6;
			}

			.ap-install-gate__footer a {
				color: #2997ff;
			}
		}
	</style>
</head>
<body>
	<main class="ap-install-gate" role="main" data-testid="ap-install-gate">
		<p class="ap-install-gate__eyebrow">{{ __( 'Visual Editor' ) }}</p>
		<h1 class="ap-install-gate__title">
			{{ __( 'Install cms-framework to enable the site editor' ) }}
		</h1>
		<p class="ap-install-gate__lede">
			{!! __( 'The visual-editor\'s site editor requires :package for templates, patterns, global styles, and menus. Install it, then reload this page:', [ 'package' => '<code>artisanpack-ui/cms-framework</code>' ] ) !!}
		</p>
		<pre class="ap-install-gate__command" aria-label="{{ __( 'Composer install command' ) }}"><code>composer require artisanpack-ui/cms-framework</code></pre>
		<p class="ap-install-gate__footer">
			{!! __( 'The post editor remains available at :link while you set this up.', [ 'link' => '<a href="' . e( $postEditorUrl ) . '" data-testid="ap-install-gate-post-editor-link">' . e( $postEditorUrl ) . '</a>' ] ) !!}
		</p>
	</main>
</body>
</html>
