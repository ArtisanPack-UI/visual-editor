/**
 * Tests for the icon block's shared render helpers.
 */

import { describe, it, expect } from 'vitest';

import {
    composeRel,
    computeIconStyle,
    computeTransform,
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
            sizeUnit: 'vh' as never,
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
    it( 'emits width and height from size + unit', () => {
        const style = computeIconStyle( { ...baseAttrs, size: 24, sizeUnit: 'rem' } );
        expect( style.width ).toBe( '24rem' );
        expect( style.height ).toBe( '24rem' );
    } );

    it( 'omits color and backgroundColor when unset', () => {
        const style = computeIconStyle( baseAttrs );
        expect( style.color ).toBeUndefined();
        expect( style.backgroundColor ).toBeUndefined();
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
