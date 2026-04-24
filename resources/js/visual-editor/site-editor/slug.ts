/**
 * Slug input normalizer.
 *
 * Run on every keystroke in the new-template / new-template-part slug
 * fields so the live value always matches what the backend stores: all
 * lowercase, with whitespace runs collapsed to a single dash. Keeps
 * punctuation untouched — an invalid character triggers the backend
 * validation rather than silently disappearing under the user's cursor.
 */
export function normalizeSlugInput(value: string): string {
    return value.toLowerCase().replace(/\s+/g, '-');
}
