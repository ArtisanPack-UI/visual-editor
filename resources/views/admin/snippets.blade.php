{{--
	Snippets admin page.

	Standalone Blade page (no Vite bundle) so a host app running the
	visual-editor without its SPA bundle can still manage snippets.
	Actions POST to /visual-editor/api/snippets via fetch — all
	admin operations use the session's existing auth stack.

	@since 1.4.0
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ __('Snippets — Visual Editor') }}</title>
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<style>
		body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; background: #f5f6f7; color: #1f2933; }
		.wrap { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
		h1 { margin-top: 0; }
		.card { background: #fff; border: 1px solid #dcdfe4; border-radius: 6px; padding: 20px; margin-bottom: 24px; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #ececec; }
		button, .btn { cursor: pointer; padding: 6px 12px; border: 1px solid #ccc; background: #f7f7f7; border-radius: 4px; font-size: 14px; }
		button.primary { background: #2271b1; color: #fff; border-color: #2271b1; }
		button.danger { background: #d63638; color: #fff; border-color: #d63638; }
		input[type=text], textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: inherit; }
		textarea { min-height: 200px; font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: 13px; }
		.field { margin-bottom: 12px; }
		.field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.error { color: #d63638; margin: 8px 0; }
		.hint { color: #666; font-size: 12px; margin-top: 4px; }
	</style>
</head>
<body>
	<div class="wrap">
		<h1>{{ __('Snippets') }}</h1>
		<p>{{ __('Reusable block-tree fragments used by the artisanpack/snippet block. Edits propagate to every placement.') }}</p>

		<div class="card">
			<h2>{{ __('Create a snippet') }}</h2>
			<form id="ve-snippet-create">
				<div class="field">
					<label for="new-slug">{{ __('Slug') }}</label>
					<input type="text" id="new-slug" required pattern="^[a-z][a-z0-9_]{0,63}$">
					<div class="hint">{{ __('Lowercase letter first; letters, digits, underscore only.') }}</div>
				</div>
				<div class="field">
					<label for="new-title">{{ __('Title') }}</label>
					<input type="text" id="new-title">
				</div>
				<div class="field">
					<label for="new-blocks">{{ __('Blocks (JSON tree)') }}</label>
					<textarea id="new-blocks" placeholder='[]'>[]</textarea>
					<div class="hint">{{ __('Advanced: paste a serialized block tree, or leave empty and edit in the editor.') }}</div>
				</div>
				<button type="submit" class="primary">{{ __('Create') }}</button>
				<div class="error" id="create-error"></div>
			</form>
		</div>

		<div class="card">
			<h2>{{ __('Existing snippets') }}</h2>
			<table id="ve-snippet-list">
				<thead>
					<tr>
						<th>{{ __('Slug') }}</th>
						<th>{{ __('Title') }}</th>
						<th>{{ __('Updated') }}</th>
						<th>{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<script>
		(function () {
			const apiBase = @json($apiBase);
			const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
			const listBody = document.querySelector('#ve-snippet-list tbody');
			const createForm = document.querySelector('#ve-snippet-create');
			const createError = document.querySelector('#create-error');

			function esc(str) {
				return String(str ?? '').replace(/[&<>"']/g, (c) => ({
					'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
				})[c]);
			}

			async function refresh() {
				const res = await fetch(apiBase, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
				const body = await res.json();
				const rows = Array.isArray(body?.data) ? body.data : [];
				listBody.innerHTML = rows.map((s) => `
					<tr>
						<td><code>${esc(s.slug)}</code></td>
						<td>${esc(s.title)}</td>
						<td>${esc(s.updated_at ?? '')}</td>
						<td>
							<button data-action="delete" data-id="${s.id}" class="danger">Delete</button>
						</td>
					</tr>
				`).join('') || '<tr><td colspan="4"><em>No snippets yet.</em></td></tr>';
			}

			createForm.addEventListener('submit', async (e) => {
				e.preventDefault();
				createError.textContent = '';
				const payload = {
					slug: document.getElementById('new-slug').value,
					title: document.getElementById('new-title').value,
					blocks: JSON.parse(document.getElementById('new-blocks').value || '[]'),
				};
				const res = await fetch(apiBase, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						Accept: 'application/json',
						'X-CSRF-TOKEN': csrf,
					},
					body: JSON.stringify(payload),
				});
				if (!res.ok) {
					const err = await res.json().catch(() => ({}));
					createError.textContent = err?.message || `HTTP ${res.status}`;
					return;
				}
				createForm.reset();
				document.getElementById('new-blocks').value = '[]';
				refresh();
			});

			listBody.addEventListener('click', async (e) => {
				const target = e.target;
				if (!(target instanceof HTMLElement) || target.dataset.action !== 'delete') return;
				const id = target.dataset.id;
				if (!confirm('Delete this snippet? It will disappear from every page that references it.')) return;
				await fetch(`${apiBase}/${id}`, {
					method: 'DELETE',
					credentials: 'same-origin',
					headers: { 'X-CSRF-TOKEN': csrf },
				});
				refresh();
			});

			refresh();
		})();
	</script>
</body>
</html>
