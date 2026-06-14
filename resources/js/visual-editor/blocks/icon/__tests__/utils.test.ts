/**
 * Tests for the icon block's shared render helpers.
 */

import { describe, it, expect } from 'vitest';

import {
    composeRel,
    computeIconStyle,
    computeTransform,
    computeWrapperStyle,
    hasDecorativeLinkConflict,
    normalizeAttributes,
    normalizeLinkTarget,
    shouldRenderLink,
} from '../utils';
import type { NormalizedIconAttributes } from '../types';

const baseAttrs: NormalizedIconAttributes = {
    iconRef: null,
    customSvg: '',
    size: 32,
    sizeUnit: 'px',
    width: 32,
    widthUnit: 'px',
    widthExplicit: false,
    height: 32,
    heightUnit: 'px',
    heightExplicit: false,
    color: '',
    backgroundColor: '',
    iconColor: '',
    rotation: 0,
    flipH: false,
    flipV: false,
    link: '',
    linkTarget: '',
    linkRel: '',
    titleAttr: '',
    ariaLabel: '',
    isDecorative: false,
    style: {},
};

describe( 'normalizeAttributes', () => {
    it( 'turns null/undefined string fields into empty strings', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: null,
            size: null,
            sizeUnit: null,
            color: null,
            backgroundColor: null,
            rotation: null,
            flipH: null,
            flipV: null,
            link: null,
            linkTarget: null,
            linkRel: null,
            titleAttr: null,
            ariaLabel: null,
            isDecorative: null,
        } );

        // The original crash: edit.tsx called `.trim()` on customSvg
        // when it deserialized as null. Verify the normalizer guards
        // every string field by trim()-ing them all.
        expect( () => {
            normalized.customSvg.trim();
            normalized.link.trim();
            normalized.linkTarget.trim();
            normalized.linkRel.trim();
            normalized.titleAttr.trim();
            normalized.ariaLabel.trim();
            normalized.color.trim();
            normalized.backgroundColor.trim();
        } ).not.toThrow();
    } );

    it( 'clamps size to the 1..1024 range mirroring the server', () => {
        const small = normalizeAttributes( {
            ...baseAttrs,
            size: -50,
        } as never );
        const huge = normalizeAttributes( {
            ...baseAttrs,
            size: 99999,
        } as never );

        expect( small.size ).toBe( 1 );
        expect( huge.size ).toBe( 1024 );
    } );

    it( 'falls back to safe defaults for invalid size/sizeUnit/rotation', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: '',
            size: NaN,
            sizeUnit: 'pt' as never,
            color: '',
            backgroundColor: '',
            rotation: 45 as never,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );

        expect( normalized.size ).toBe( 32 );
        expect( normalized.sizeUnit ).toBe( 'px' );
        expect( normalized.rotation ).toBe( 0 );
    } );

    it( 'rejects malformed iconRef objects', () => {
        const normalized = normalizeAttributes( {
            iconRef: { set: '', name: 'github' } as never,
            customSvg: '',
            size: 32,
            sizeUnit: 'px',
            color: '',
            backgroundColor: '',
            rotation: 0,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );

        expect( normalized.iconRef ).toBeNull();
    } );
} );

describe( 'computeIconStyle', () => {
    it( 'emits width and height from the normalized width/height + units', () => {
        const style = computeIconStyle( {
            ...baseAttrs,
            width: 24,
            widthUnit: 'rem',
            height: 24,
            heightUnit: 'rem',
        } );
        expect( style.width ).toBe( '24rem' );
        expect( style.height ).toBe( '24rem' );
    } );

    it( 'omits color when no icon color or legacy fallback is set', () => {
        const style = computeIconStyle( baseAttrs );
        expect( style.color ).toBeUndefined();
        // Background no longer flows through this helper — WP serializes
        // it onto the wrapper via `useBlockProps()`.
        expect( style.backgroundColor ).toBeUndefined();
    } );

    it( 'applies the explicit iconColor as the body-span color', () => {
        const style = computeIconStyle( { ...baseAttrs, iconColor: '#abc' } );
        expect( style.color ).toBe( '#abc' );
    } );

    it( 'lets iconColor override the WP style.color.text fallback', () => {
        const style = computeIconStyle( {
            ...baseAttrs,
            iconColor: '#abc',
            style: { color: { text: '#def' } },
        } );
        expect( style.color ).toBe( '#abc' );
    } );

    it( 'falls back to style.color.text when iconColor is empty', () => {
        const style = computeIconStyle( {
            ...baseAttrs,
            style: { color: { text: '#def' } },
        } );
        expect( style.color ).toBe( '#def' );
    } );

    it( 'falls back to the legacy top-level color attribute last', () => {
        const style = computeIconStyle( { ...baseAttrs, color: '#333' } );
        expect( style.color ).toBe( '#333' );
    } );

    it( 'reads independent width and height from per-axis overrides', () => {
        const style = computeIconStyle( {
            ...baseAttrs,
            width: 48,
            widthUnit: 'px',
            height: 24,
            heightUnit: 'rem',
        } );
        expect( style.width ).toBe( '48px' );
        expect( style.height ).toBe( '24rem' );
    } );

    it( 'drops a style value that fails the safe-CSS allowlist', () => {
        const style = computeIconStyle( {
            ...baseAttrs,
            style: { color: { text: 'expression(alert(1))' } },
        } );
        expect( style.color ).toBeUndefined();
    } );
} );

describe( 'computeWrapperStyle', () => {
    it( 'returns an empty object — WP serializes wrapper styles via useBlockProps()', () => {
        expect( computeWrapperStyle( baseAttrs ) ).toEqual( {} );
        expect(
            computeWrapperStyle( {
                ...baseAttrs,
                style: { spacing: { margin: '8px' } },
            } ),
        ).toEqual( {} );
    } );
} );

describe( 'normalizeAttributes width/height override', () => {
    it( 'leaves width and height equal to size when both unset', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: '',
            size: 48,
            sizeUnit: 'px',
            color: '',
            backgroundColor: '',
            rotation: 0,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );
        expect( normalized.width ).toBe( 48 );
        expect( normalized.widthUnit ).toBe( 'px' );
        expect( normalized.widthExplicit ).toBe( false );
        expect( normalized.height ).toBe( 48 );
        expect( normalized.heightUnit ).toBe( 'px' );
        expect( normalized.heightExplicit ).toBe( false );
    } );

    it( 'lets width override size on a single axis without disturbing height', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: '',
            size: 32,
            sizeUnit: 'px',
            width: 64,
            widthUnit: 'px',
            color: '',
            backgroundColor: '',
            rotation: 0,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );
        expect( normalized.width ).toBe( 64 );
        expect( normalized.widthExplicit ).toBe( true );
        expect( normalized.height ).toBe( 32 );
        expect( normalized.heightExplicit ).toBe( false );
    } );

    it( 'inherits sizeUnit for width when widthUnit is null', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: '',
            size: 32,
            sizeUnit: 'rem',
            width: 4,
            widthUnit: null,
            color: '',
            backgroundColor: '',
            rotation: 0,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );
        expect( normalized.widthUnit ).toBe( 'rem' );
    } );

    it( 'clamps explicit width and height to the 1..1024 range', () => {
        const normalized = normalizeAttributes( {
            iconRef: null,
            customSvg: '',
            size: 32,
            sizeUnit: 'px',
            width: -10,
            height: 99999,
            color: '',
            backgroundColor: '',
            rotation: 0,
            flipH: false,
            flipV: false,
            link: '',
            linkTarget: '',
            linkRel: '',
            titleAttr: '',
            ariaLabel: '',
            isDecorative: false,
        } );
        expect( normalized.width ).toBe( 1 );
        expect( normalized.height ).toBe( 1024 );
    } );
} );

describe( 'computeTransform', () => {
    it( 'returns undefined when no transforms apply', () => {
        expect( computeTransform( baseAttrs ) ).toBeUndefined();
    } );

    it( 'composes rotation and flips', () => {
        const t = computeTransform( {
            ...baseAttrs,
            rotation: 180,
            flipH: true,
            flipV: true,
        } );
        expect( t ).toContain( 'rotate(180deg)' );
        expect( t ).toContain( 'scaleX(-1)' );
        expect( t ).toContain( 'scaleY(-1)' );
    } );
} );

describe( 'shouldRenderLink', () => {
    it( 'requires both a link AND an icon', () => {
        expect( shouldRenderLink( { ...baseAttrs, link: 'https://example.com' } ) ).toBe( false );
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'https://example.com', iconRef: { set: 'fab', name: 'github' } } )
        ).toBe( true );
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'https://example.com', customSvg: '<svg></svg>' } )
        ).toBe( true );
    } );

    it( 'rejects whitespace-only links', () => {
        expect(
            shouldRenderLink( { ...baseAttrs, link: '   ', iconRef: { set: 'fab', name: 'github' } } )
        ).toBe( false );
    } );

    it( 'rejects javascript: and data: schemes', () => {
        const icon = { set: 'fab', name: 'github' };
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'javascript:alert(1)', iconRef: icon } )
        ).toBe( false );
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'data:text/html,<script>', iconRef: icon } )
        ).toBe( false );
    } );

    it( 'allows relative, mailto, tel, and hash links', () => {
        const icon = { set: 'fab', name: 'github' };
        expect( shouldRenderLink( { ...baseAttrs, link: '/about', iconRef: icon } ) ).toBe( true );
        expect( shouldRenderLink( { ...baseAttrs, link: '#anchor', iconRef: icon } ) ).toBe( true );
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'mailto:hi@x.com', iconRef: icon } )
        ).toBe( true );
        expect(
            shouldRenderLink( { ...baseAttrs, link: 'tel:+15551234567', iconRef: icon } )
        ).toBe( true );
    } );
} );

describe( 'normalizeLinkTarget', () => {
    it( 'passes through the four standard targets', () => {
        expect( normalizeLinkTarget( '_blank' ) ).toBe( '_blank' );
        expect( normalizeLinkTarget( '_self' ) ).toBe( '_self' );
        expect( normalizeLinkTarget( '_parent' ) ).toBe( '_parent' );
        expect( normalizeLinkTarget( '_top' ) ).toBe( '_top' );
    } );

    it( 'drops anything else', () => {
        expect( normalizeLinkTarget( '_evil' ) ).toBe( '' );
        expect( normalizeLinkTarget( '' ) ).toBe( '' );
    } );
} );

describe( 'composeRel', () => {
    it( 'forces noopener noreferrer on target=_blank', () => {
        const rel = composeRel( '_blank', 'nofollow' );
        expect( rel ).toContain( 'noopener' );
        expect( rel ).toContain( 'noreferrer' );
        expect( rel ).toContain( 'nofollow' );
    } );

    it( 'deduplicates rel tokens', () => {
        const rel = composeRel( '_blank', 'noopener nofollow' );
        const tokens = rel.split( ' ' );
        const unique = new Set( tokens );
        expect( tokens.length ).toBe( unique.size );
    } );

    it( 'leaves rel untouched for non-blank targets', () => {
        expect( composeRel( '_self', 'sponsored' ) ).toBe( 'sponsored' );
    } );
} );

describe( 'hasDecorativeLinkConflict', () => {
    it( 'flags decorative + linked + no aria-label', () => {
        const conflict = hasDecorativeLinkConflict( {
            ...baseAttrs,
            isDecorative: true,
            link: 'https://example.com',
            iconRef: { set: 'fab', name: 'github' },
        } );
        expect( conflict ).toBe( true );
    } );

    it( 'clears once an aria-label is provided', () => {
        const conflict = hasDecorativeLinkConflict( {
            ...baseAttrs,
            isDecorative: true,
            link: 'https://example.com',
            ariaLabel: 'Visit on GitHub',
            iconRef: { set: 'fab', name: 'github' },
        } );
        expect( conflict ).toBe( false );
    } );

    it( 'is false when there is no link', () => {
        const conflict = hasDecorativeLinkConflict( {
            ...baseAttrs,
            isDecorative: true,
            iconRef: { set: 'fab', name: 'github' },
        } );
        expect( conflict ).toBe( false );
    } );
} );
