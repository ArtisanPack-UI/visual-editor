{{-- Phase 6 (#557) — admin icon-sets settings page.

	Server-rendered list + plain HTML forms targeting the
	`/visual-editor/api/admin/icon-sets` endpoints. The page is gated
	by the same `SiteEditorAccessGate` binding that protects the
	site-editor SPA mount, so reaching this view at all implies the
	visitor has cleared the visual-editor management policy.

	Inline styles deliberately — the SPA's Vite bundle isn't loaded
	here, so the page must render without it. Mirrors the install-gate
	page (#432). --}}
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ __( 'Icon Sets — ArtisanPack Visual Editor' ) }}</title>
	<style>
		:root { color-scheme: light dark; }

		body {
			margin: 0;
			min-height: 100vh;
			padding: 2rem;
			background: #f5f5f7;
			color: #1d1d1f;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			line-height: 1.5;
		}

		.ap-settings {
			max-width: 48rem;
			margin: 0 auto;
		}

		.ap-settings__header {
			margin-bottom: 1.5rem;
		}

		.ap-settings__header h1 {
			margin: 0 0 0.25rem 0;
			font-size: 1.5rem;
		}

		.ap-settings__header p {
			margin: 0;
			color: #6e6e73;
		}

		.ap-card {
			background: #fff;
			border: 1px solid #e5e5ea;
			border-radius: 12px;
			padding: 1.25rem 1.5rem;
			margin-bottom: 1.25rem;
		}

		.ap-card h2 {
			margin: 0 0 0.75rem 0;
			font-size: 1.125rem;
		}

		.ap-form-row {
			display: grid;
			grid-template-columns: 9rem 1fr;
			gap: 0.5rem 0.75rem;
			align-items: center;
			margin-bottom: 0.75rem;
		}

		.ap-form-row label {
			color: #424245;
			font-weight: 500;
		}

		.ap-form-row input[type="text"],
		.ap-form-row input[type="file"] {
			width: 100%;
			padding: 0.5rem 0.625rem;
			border: 1px solid #d2d2d7;
			border-radius: 8px;
			font-size: 0.95rem;
			background: #fff;
			color: inherit;
		}

		.ap-actions {
			display: flex;
			gap: 0.5rem;
			justify-content: flex-end;
		}

		.ap-button {
			padding: 0.5rem 1rem;
			border-radius: 8px;
			border: 1px solid transparent;
			font-size: 0.95rem;
			cursor: pointer;
			background: #0071e3;
			color: #fff;
		}

		.ap-button--secondary {
			background: #fff;
			color: #1d1d1f;
			border-color: #d2d2d7;
		}

		.ap-button--danger {
			background: #d70015;
		}

		.ap-table {
			width: 100%;
			border-collapse: collapse;
		}

		.ap-table th,
		.ap-table td {
			text-align: left;
			padding: 0.65rem 0.5rem;
			border-bottom: 1px solid #e5e5ea;
			vertical-align: middle;
		}

		.ap-table th {
			font-size: 0.75rem;
			text-transform: uppercase;
			letter-spacing: 0.04em;
			color: #6e6e73;
			font-weight: 600;
		}

		.ap-table tr:last-child td { border-bottom: 0; }

		.ap-empty {
			padding: 1rem;
			color: #6e6e73;
			text-align: center;
			font-style: italic;
		}

		.ap-feedback {
			margin: 0 0 1rem 0;
			padding: 0.75rem 1rem;
			border-radius: 8px;
			background: #eaf3ff;
			border: 1px solid #b8d4fe;
			color: #003580;
			font-size: 0.9rem;
		}

		/* Visually hide the feedback region while it has no message —
		   `aria-live` regions cannot be `hidden`, so we drop them out
		   of layout with CSS instead. */
		[role="status"][aria-hidden="true"] {
			position: absolute;
			width: 1px;
			height: 1px;
			padding: 0;
			margin: -1px;
			overflow: hidden;
			clip: rect(0, 0, 0, 0);
			white-space: nowrap;
			border: 0;
		}

		.ap-feedback--error {
			background: #fff0f0;
			border-color: #fcc;
			color: #8a0010;
		}

		.ap-row-actions form { display: inline; }

		@media (prefers-color-scheme: dark) {
			body { background: #1d1d1f; color: #f5f5f7; }
			.ap-card { background: #2c2c2e; border-color: #3a3a3c; }
			.ap-settings__header p,
			.ap-table th { color: #8e8e93; }
			.ap-form-row input[type="text"],
			.ap-form-row input[type="file"] {
				background: #1d1d1f; border-color: #3a3a3c; color: #f5f5f7;
			}
			.ap-button--secondary { background: #2c2c2e; color: #f5f5f7; border-color: #3a3a3c; }
		}
	</style>
</head>
<body>
	<main class="ap-settings">
		<header class="ap-settings__header">
			<h1>{{ __( 'Icon Sets' ) }}</h1>
			<p>{{ __( 'Upload licensed SVG icon sets (e.g. Font Awesome Pro) for use in the Icon block.' ) }}</p>
		</header>

		<div id="ap-feedback" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true"></div>

		<section class="ap-card">
			<h2>{{ __( 'Upload a new set' ) }}</h2>
			<form id="ap-icon-sets-upload" enctype="multipart/form-data">
				<div class="ap-form-row">
					<label for="ap-prefix">{{ __( 'Prefix' ) }}</label>
					<input id="ap-prefix" name="prefix" type="text" required
					       autocomplete="off" pattern="[a-z0-9][a-z0-9_-]{1,31}"
					       placeholder="fa-pro">
				</div>
				<div class="ap-form-row">
					<label for="ap-label">{{ __( 'Label' ) }}</label>
					<input id="ap-label" name="label" type="text" required maxlength="64"
					       placeholder="{{ __( 'Font Awesome Pro' ) }}">
				</div>
				<div class="ap-form-row">
					<label for="ap-zip">{{ __( 'Zip archive' ) }}</label>
					<input id="ap-zip" name="zip" type="file" accept=".zip,application/zip" required>
				</div>
				<p style="margin:0 0 0.75rem 0; color:#6e6e73; font-size:0.85rem;">
					{{ __( 'Maximum size: :size MB. Non-SVG entries are skipped; each SVG runs through the package sanitizer.', [ 'size' => intdiv( $maxKilobytes, 1024 ) ] ) }}
				</p>
				<div class="ap-actions">
					<button type="submit" class="ap-button">{{ __( 'Upload' ) }}</button>
				</div>
			</form>
		</section>

		<section class="ap-card">
			<h2>{{ __( 'Registered sets' ) }}</h2>
			@if ( empty( $sets ) )
				<p class="ap-empty">{{ __( 'No uploaded icon sets yet.' ) }}</p>
			@else
				<table class="ap-table">
					<thead>
						<tr>
							<th>{{ __( 'Prefix' ) }}</th>
							<th>{{ __( 'Label' ) }}</th>
							<th>{{ __( 'Uploaded' ) }}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@foreach ( $sets as $set )
							<tr>
								<td><code>{{ $set->prefix }}</code></td>
								<td>{{ $set->label }}</td>
								<td>{{ $set->createdAt }}</td>
								<td class="ap-row-actions" style="text-align:right;">
									<button type="button" class="ap-button ap-button--secondary"
									        data-action="rename"
									        data-prefix="{{ $set->prefix }}"
									        data-label="{{ $set->label }}">
										{{ __( 'Rename' ) }}
									</button>
									<button type="button" class="ap-button ap-button--danger"
									        data-action="delete"
									        data-prefix="{{ $set->prefix }}">
										{{ __( 'Delete' ) }}
									</button>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			@endif
		</section>
	</main>

	<script>
		(function () {
			'use strict';

			var apiBase = @json( $apiBase );
			var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			var feedback = document.getElementById('ap-feedback');

			function showFeedback(message, isError) {
				feedback.textContent = message;
				feedback.className = 'ap-feedback' + (isError ? ' ap-feedback--error' : '');
				feedback.setAttribute('aria-hidden', 'false');
			}

			async function reload() {
				window.location.reload();
			}

			document.getElementById('ap-icon-sets-upload').addEventListener('submit', async function (event) {
				event.preventDefault();
				var formData = new FormData(event.target);
				try {
					var res = await fetch(apiBase, {
						method: 'POST',
						headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
						body: formData,
						credentials: 'same-origin',
					});
					var body = await res.json().catch(function () { return {}; });
					if (!res.ok) {
						showFeedback(body.message || 'Upload failed.', true);
						return;
					}
					showFeedback('Uploaded ' + ((body.report && body.report.stored && body.report.stored.length) || 0) + ' icon(s).');
					setTimeout(reload, 800);
				} catch (err) {
					showFeedback(String(err), true);
				}
			});

			document.querySelectorAll('button[data-action="rename"]').forEach(function (btn) {
				btn.addEventListener('click', async function () {
					var prefix = btn.getAttribute('data-prefix');
					var current = btn.getAttribute('data-label');
					var next = window.prompt('New label for "' + prefix + '":', current);
					if (next === null || next.trim() === '' || next === current) {
						return;
					}
					try {
						var res = await fetch(apiBase + '/' + encodeURIComponent(prefix), {
							method: 'PATCH',
							headers: {
								'X-CSRF-TOKEN': csrfToken,
								'Accept': 'application/json',
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({ label: next }),
							credentials: 'same-origin',
						});
						if (!res.ok) {
							var body = await res.json().catch(function () { return {}; });
							showFeedback(body.message || 'Rename failed.', true);
							return;
						}
						reload();
					} catch (err) {
						showFeedback(String(err), true);
					}
				});
			});

			document.querySelectorAll('button[data-action="delete"]').forEach(function (btn) {
				btn.addEventListener('click', async function () {
					var prefix = btn.getAttribute('data-prefix');
					if (!window.confirm('Delete icon set "' + prefix + '"? This cannot be undone.')) {
						return;
					}
					try {
						var res = await fetch(apiBase + '/' + encodeURIComponent(prefix), {
							method: 'DELETE',
							headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
							credentials: 'same-origin',
						});
						if (!res.ok && res.status !== 204) {
							var body = await res.json().catch(function () { return {}; });
							showFeedback(body.message || 'Delete failed.', true);
							return;
						}
						reload();
					} catch (err) {
						showFeedback(String(err), true);
					}
				});
			});
		}());
	</script>
</body>
</html>
