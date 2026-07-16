/**
 * Client-side wrapper around `/visual-editor/api/users/search`.
 *
 * Called by the Specific User rule's autocomplete input. Kept in its
 * own module so tests can stub it via Vitest's module mocking without
 * having to reach into `VisibilityPanel.tsx`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import type { SpecificUserRef } from './types';

export async function searchUsers(term: string, limit = 10): Promise<SpecificUserRef[]> {
    const params = new URLSearchParams({ q: term, limit: String(limit) });

    try {
        const response = await fetch(`/visual-editor/api/users/search?${params.toString()}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return [];
        }

        const payload = await response.json();

        if (payload === null || typeof payload !== 'object' || !Array.isArray(payload.data)) {
            return [];
        }

        return payload.data
            .filter((row: unknown): row is { id: unknown; email: unknown; name?: unknown } =>
                row !== null && typeof row === 'object' && 'id' in row && 'email' in row
            )
            .map((row) => ({
                id: Number(row.id),
                email: String(row.email ?? ''),
                name: typeof row.name === 'string' ? row.name : String(row.email ?? ''),
            }))
            .filter((u: SpecificUserRef) => Number.isFinite(u.id));
    } catch (_e) {
        return [];
    }
}
