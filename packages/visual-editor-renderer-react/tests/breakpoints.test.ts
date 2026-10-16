/**
 * Host-configurable device breakpoints (M-17). `setBreakpoints()` takes
 * the server's `BreakpointRegistry::toArray()` (or a config-style map),
 * and the responsive and column-width emitters use the resulting Tablet
 * / Mobile max-widths and preview widths exactly as Blade does.
 */

import { afterEach, describe, expect, it } from 'vitest';

import * as reactColumnWidth from '../src/support/columnWidth';
import * as reactResponsive from '../src/support/responsive';
import * as reactVisibility from '../src/visibility';
import * as vueColumnWidth from '../../visual-editor-renderer-vue/src/support/columnWidth';
import * as vueResponsive from '../../visual-editor-renderer-vue/src/support/responsive';
import * as vueVisibility from '../../visual-editor-renderer-vue/src/visibility';

/**
 * `BreakpointRegistry::fromLayers( [ 'tablet' => '700px', 'mobile' =>
 * [ 'maxWidthPx' => 599, 'previewWidthPx' => 375 ] ] )->toArray()`.
 */
const SERVER_REGISTRY = [
    { key: 'sm', minWidthPx: 640, previewWidthPx: 375, label: 'Mobile' },
    { key: 'md', minWidthPx: 768, previewWidthPx: 768, label: 'Tablet' },
    { key: 'lg', minWidthPx: 1024, previewWidthPx: 1440, label: 'Desktop' },
    { key: 'xl', minWidthPx: 1280, previewWidthPx: 1280, label: 'xl+' },
    { key: '2xl', minWidthPx: 1536, previewWidthPx: 1536, label: '2xl+' },
    { key: 'tablet', maxWidthPx: 700, previewWidthPx: 700, label: 'Tablet' },
    { key: 'mobile', maxWidthPx: 599, previewWidthPx: 375, label: 'Mobile' },
];

const DEFAULT_LEGACY = [
    { key: 'sm', minWidthPx: 640 },
    { key: 'md', minWidthPx: 768 },
    { key: 'lg', minWidthPx: 1024 },
    { key: 'xl', minWidthPx: 1280 },
    { key: '2xl', minWidthPx: 1536 },
];

const DEFAULT_DEVICES = [
    { key: 'tablet', maxWidthPx: 1023, previewWidthPx: 768 },
    { key: 'mobile', maxWidthPx: 767, previewWidthPx: 375 },
];

describe.each([
    ['react', reactVisibility, reactResponsive, reactColumnWidth],
    ['vue', vueVisibility, vueResponsive, vueColumnWidth],
])('%s breakpoints', (_name, visibility, responsive, columnWidth) => {
    afterEach(() => {
        visibility.setBreakpoints([...DEFAULT_LEGACY, ...DEFAULT_DEVICES]);
    });

    it('defaults to the registry defaults', () => {
        expect(visibility.getBreakpoints()).toEqual(DEFAULT_LEGACY);
        expect(visibility.getDeviceBreakpoints()).toEqual(DEFAULT_DEVICES);
    });

    it('layers a config-style map over the defaults', () => {
        visibility.setBreakpoints({ mobile: { maxWidthPx: 599, previewWidthPx: 375 } });

        expect(visibility.getBreakpoints()).toEqual(DEFAULT_LEGACY);
        expect(visibility.getDeviceBreakpoints()).toEqual([
            { key: 'tablet', maxWidthPx: 1023, previewWidthPx: 768 },
            { key: 'mobile', maxWidthPx: 599, previewWidthPx: 375 },
        ]);
        expect(responsive.compileResponsive({ responsive: { 'style.spacing.padding': { mobile: '1rem' } } })?.css).toMatch(
            /^@media \(max-width:599px\)\{/
        );
    });

    it('keeps the family of a scalar override and clamps the inherited preview width', () => {
        visibility.setBreakpoints({ tablet: '700px', sm: 600, xl: null });

        expect(visibility.getDeviceBreakpoints()).toEqual([
            { key: 'mobile', maxWidthPx: 767, previewWidthPx: 375 },
            { key: 'tablet', maxWidthPx: 700, previewWidthPx: 700 },
        ]);
        expect(visibility.getBreakpoints().map((bp) => `${bp.key}:${bp.minWidthPx}`)).toEqual([
            'sm:600',
            'md:768',
            'lg:1024',
            '2xl:1536',
        ]);
    });

    it('takes the server registry array and matches its media queries and cascade', () => {
        visibility.setBreakpoints(SERVER_REGISTRY);

        expect(visibility.getDeviceBreakpoints()).toEqual([
            { key: 'tablet', maxWidthPx: 700, previewWidthPx: 700 },
            { key: 'mobile', maxWidthPx: 599, previewWidthPx: 375 },
        ]);

        // Tablet previews at 700px, so it no longer inherits `md` (768px):
        // BreakpointRegistry::cascade( 'tablet' ) is [ tablet, sm, base ].
        const scope = responsive.compileResponsive({
            minHeight: 50,
            minHeightUnit: 'vh',
            responsive: { minHeight: { md: 80 }, minHeightUnit: { tablet: 'px' } },
        });

        expect(scope?.css).toContain('@media (max-width:700px){');
        expect(scope?.css).toContain('min-height:50px!important');
        expect(scope?.css).not.toContain('min-height:80px');

        expect(columnWidth.columnWidthScope({ width: '50%', responsive: { width: { mobile: '100%' } } })?.css).toContain(
            '@media (max-width:599px)'
        );
    });

    it('keeps the default devices when an array has no device entries', () => {
        visibility.setBreakpoints([{ key: 'md', minWidthPx: 900 }]);

        expect(visibility.getBreakpoints()).toEqual([{ key: 'md', minWidthPx: 900 }]);
        expect(visibility.getDeviceBreakpoints()).toEqual(DEFAULT_DEVICES);
    });

    it('drops entries the server registry would reject', () => {
        visibility.setBreakpoints([
            { key: 'base', minWidthPx: 100 },
            { key: 'bad key', minWidthPx: 200 },
            { key: 'both', minWidthPx: 300, maxWidthPx: 300 },
            { key: 'zero', minWidthPx: 0 },
            { key: 'sm', minWidthPx: 640 },
            { key: 'dupe', minWidthPx: '640px' },
            { key: 'tablet', maxWidthPx: '900px', previewWidthPx: 1200 },
            { key: 'mobile', maxWidthPx: 900 },
        ]);

        expect(visibility.getBreakpoints()).toEqual([{ key: 'sm', minWidthPx: 640 }]);
        expect(visibility.getDeviceBreakpoints()).toEqual([{ key: 'tablet', maxWidthPx: 900, previewWidthPx: 900 }]);
    });
});
