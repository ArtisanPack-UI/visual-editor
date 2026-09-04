/**
 * Tests for the editor-side `safeIframeUrl` scheme allow-list (#761).
 */

import { describe, it, expect } from 'vitest';

import { safeIframeUrl } from '../safe-url';

describe('safeIframeUrl', () => {
    it('passes https URLs through unchanged', () => {
        expect(safeIframeUrl('https://www.openstreetmap.org/x')).toBe(
            'https://www.openstreetmap.org/x'
        );
    });

    it('drops http URLs to an empty string (mixed-content risk)', () => {
        expect(safeIframeUrl('http://example.test/map')).toBe('');
    });

    it('drops javascript: URLs to an empty string', () => {
        expect(safeIframeUrl('javascript:alert(1)')).toBe('');
        expect(safeIframeUrl('  JAVASCRIPT:alert(1)  ')).toBe('');
    });

    it('drops data: URLs to an empty string', () => {
        expect(safeIframeUrl('data:text/html,<script>alert(1)</script>')).toBe('');
    });

    it('drops vbscript: URLs to an empty string', () => {
        expect(safeIframeUrl('vbscript:msgbox(1)')).toBe('');
    });

    it('drops non-string / empty / relative inputs to an empty string', () => {
        expect(safeIframeUrl(undefined)).toBe('');
        expect(safeIframeUrl(null)).toBe('');
        expect(safeIframeUrl(42)).toBe('');
        expect(safeIframeUrl('')).toBe('');
        expect(safeIframeUrl('   ')).toBe('');
        // Relative URLs also denied — a map iframe from a relative host
        // URL is not a valid use case and the strict check is preferable.
        expect(safeIframeUrl('/relative/path')).toBe('');
    });
});
