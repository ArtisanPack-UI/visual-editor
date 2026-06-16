/**
 * Post Variant — edit component (#591).
 *
 * Inspector exposes the matcher kind + value, an optional human label,
 * and a priority for tie-breaking inside a precedence tier. The body
 * is `<InnerBlocks />` — the variant template that overrides the base
 * post-template for matching posts.
 */

import type { ReactElement } from 'react';
import {
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Notice,
    PanelBody,
    RangeControl,
    SelectControl,
    TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import type { Matcher, MatcherKind } from '../../editor/variant-matcher';

interface PostVariantAttributes {
    readonly matcher?: Matcher;
    readonly priority?: number;
    readonly label?: string;
}

interface PostVariantEditProps {
    attributes: PostVariantAttributes;
    setAttributes: ( changes: Partial<PostVariantAttributes> ) => void;
}

const POSITION_PRESETS: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'first', label: 'First post' },
    { value: 'last', label: 'Last post' },
    { value: 'nth:2', label: '2nd post' },
    { value: 'nth:3', label: '3rd post' },
    { value: 'range:1-3', label: 'Posts 1–3' },
];

const PATTERN_PRESETS: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'odd', label: 'Odd posts' },
    { value: 'even', label: 'Even posts' },
    { value: 'every-nth:3', label: 'Every 3rd post' },
    { value: 'every-nth:4', label: 'Every 4th post' },
];

const META_PRESETS: ReadonlyArray<{ value: string; label: string }> = [
    { value: 'sticky', label: 'Sticky posts' },
    { value: 'featured', label: 'Featured posts' },
    { value: 'has-featured-image', label: 'Posts with a featured image' },
];

function getMatcher( attributes: PostVariantAttributes ): Matcher {
    if (
        attributes.matcher &&
        typeof attributes.matcher.kind === 'string' &&
        typeof attributes.matcher.value === 'string'
    ) {
        return attributes.matcher;
    }
    return { kind: 'position', value: 'first' };
}

function defaultValueForKind( kind: MatcherKind ): string {
    switch ( kind ) {
        case 'position':
            return 'first';
        case 'pattern':
            return 'odd';
        case 'meta':
            return 'sticky';
        case 'custom':
            return 'callback:';
    }
}

export default function PostVariantEdit( {
    attributes,
    setAttributes,
}: PostVariantEditProps ): ReactElement {
    const matcher = getMatcher( attributes );
    const priority = typeof attributes.priority === 'number' ? attributes.priority : 10;
    const label = typeof attributes.label === 'string' ? attributes.label : '';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( {
        className: 'wp-block-artisanpack-post-variant',
        'data-variant-kind': matcher.kind,
        'data-variant-value': matcher.value,
    } );

    const setMatcher = ( next: Matcher ): void => {
        setAttributes( { matcher: next } );
    };

    const handleKindChange = ( raw: string ): void => {
        const kind = raw as MatcherKind;
        if ( kind === matcher.kind ) {
            return;
        }
        setMatcher( { kind, value: defaultValueForKind( kind ) } as Matcher );
    };

    const presets =
        matcher.kind === 'position'
            ? POSITION_PRESETS
            : matcher.kind === 'pattern'
              ? PATTERN_PRESETS
              : matcher.kind === 'meta'
                ? META_PRESETS
                : null;

    const summary = buildSummary( matcher, label );

    return (
        <div { ...blockProps }>
            <InspectorControls>
                <PanelBody title={ __( 'Variant rule', TEXT_DOMAIN ) }>
                    <TextControl
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Label', TEXT_DOMAIN ) }
                        value={ label }
                        onChange={ ( value: string ) => setAttributes( { label: value } ) }
                        help={ __( 'Shown in the parent query block\'s "Post Variants" panel.', TEXT_DOMAIN ) }
                    />
                    <SelectControl
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Matcher kind', TEXT_DOMAIN ) }
                        value={ matcher.kind }
                        options={ [
                            { value: 'position', label: __( 'Position', TEXT_DOMAIN ) },
                            { value: 'pattern', label: __( 'Pattern (odd / even / every Nth)', TEXT_DOMAIN ) },
                            { value: 'meta', label: __( 'Metadata (sticky, featured, taxonomy, author)', TEXT_DOMAIN ) },
                            { value: 'custom', label: __( 'Custom callback', TEXT_DOMAIN ) },
                        ] }
                        onChange={ handleKindChange }
                    />
                    { presets !== null && (
                        <SelectControl
                            // @ts-expect-error - upstream prop
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={ __( 'Preset', TEXT_DOMAIN ) }
                            value={
                                presets.some( ( p ) => p.value === matcher.value )
                                    ? matcher.value
                                    : ''
                            }
                            options={ [
                                { value: '', label: __( 'Custom value…', TEXT_DOMAIN ) },
                                ...presets.map( ( preset ) => ( {
                                    value: preset.value,
                                    label: __( preset.label, TEXT_DOMAIN ),
                                } ) ),
                            ] }
                            onChange={ ( value: string ) => {
                                if ( '' === value ) {
                                    return;
                                }
                                setMatcher( { kind: matcher.kind, value } as Matcher );
                            } }
                        />
                    ) }
                    <TextControl
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                        label={ __( 'Matcher value', TEXT_DOMAIN ) }
                        value={ matcher.value }
                        onChange={ ( value: string ) =>
                            setMatcher( { kind: matcher.kind, value } as Matcher )
                        }
                        help={ helpFor( matcher.kind ) }
                    />
                    <RangeControl
                        __nextHasNoMarginBottom
                        __next40pxDefaultSize
                        label={ __( 'Priority', TEXT_DOMAIN ) }
                        value={ priority }
                        onChange={ ( value?: number ) =>
                            setAttributes( { priority: value ?? 10 } )
                        }
                        min={ 0 }
                        max={ 100 }
                        help={ __( 'Lower numbers run first when variants share a precedence tier.', TEXT_DOMAIN ) }
                    />
                    { matcher.kind === 'custom' && (
                        <Notice status="info" isDismissible={ false }>
                            { __( 'Custom matchers are resolved server-side by the apve_query_variant_match_<name> filter hook.', TEXT_DOMAIN ) }
                        </Notice>
                    ) }
                </PanelBody>
            </InspectorControls>
            <div className="wp-block-artisanpack-post-variant__summary" aria-hidden="true">
                <span>{ summary }</span>
            </div>
            <InnerBlocks templateLock={ false } />
        </div>
    );
}

function helpFor( kind: MatcherKind ): string {
    switch ( kind ) {
        case 'position':
            return __( 'first | last | nth:<n> | range:<from>-<to>', TEXT_DOMAIN );
        case 'pattern':
            return __( 'odd | even | every-nth:<step> | every-nth:<step>:start:<offset>', TEXT_DOMAIN );
        case 'meta':
            return __( 'sticky | featured | has-featured-image | author:<id> | taxonomy:<tax>:<slug>', TEXT_DOMAIN );
        case 'custom':
            return __( 'callback:<name> — resolved by apve_query_variant_match_<name>.', TEXT_DOMAIN );
    }
}

function buildSummary( matcher: Matcher, label: string ): string {
    const tag = `${ matcher.kind }:${ matcher.value }`;
    if ( label && '' !== label ) {
        return `${ label } · ${ tag }`;
    }
    return tag;
}
