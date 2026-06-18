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
    readonly gridColumnSpan?: number;
    readonly gridRowSpan?: number;
}

interface DisplayLayoutContext {
    readonly type?: string;
    readonly columns?: number;
}

interface PostVariantContext {
    readonly displayLayout?: DisplayLayoutContext | null;
    readonly 'artisanpack/postTemplateLayout'?: string | null;
    readonly 'artisanpack/postTemplateColumns'?: number | null;
}

interface PostVariantEditProps {
    attributes: PostVariantAttributes;
    setAttributes: ( changes: Partial<PostVariantAttributes> ) => void;
    context?: PostVariantContext;
}

// Hard ceiling matches the renderer-side CSS rule set in
// `post-variant.css` and the `QueryInliner::clampSpanValue()` cap.
// Keeping the same ceiling here means the editor slider never
// exposes a value the renderers would silently clamp away.
const HARD_MAX_SPAN = 12;
const MAX_ROW_SPAN = HARD_MAX_SPAN;
const ROW_SPAN_WARN_THRESHOLD = 4;
const DEFAULT_NUM_COLUMNS = 3;

function clampSpan( value: number | undefined, max: number, fallback: number ): number {
    const effectiveMax = Math.min( max, HARD_MAX_SPAN );
    const next = typeof value === 'number' && Number.isFinite( value )
        ? Math.trunc( value )
        : fallback;

    if ( next < 1 ) {
        return 1;
    }

    if ( next > effectiveMax ) {
        return effectiveMax;
    }

    return next;
}

function resolveNumColumns( context: PostVariantContext | undefined ): number {
    // Prefer the post-template's own `columns` attribute (exposed via
    // `artisanpack/postTemplateColumns` context) because the post-template
    // is the source of truth for the grid the variant lives inside. Fall
    // back to the parent query's `displayLayout.columns` for hosts that
    // drive grids from the query block instead.
    const fromPostTemplate = context?.[ 'artisanpack/postTemplateColumns' ];
    const fromDisplayLayout = context?.displayLayout?.columns;

    const raw = typeof fromPostTemplate === 'number'
        ? fromPostTemplate
        : fromDisplayLayout;

    const parsed = typeof raw === 'number' && Number.isFinite( raw ) ? Math.trunc( raw ) : DEFAULT_NUM_COLUMNS;

    if ( parsed < 1 ) {
        return 1;
    }

    if ( parsed > HARD_MAX_SPAN ) {
        return HARD_MAX_SPAN;
    }

    return parsed;
}

function isGridLayout( context: PostVariantContext | undefined ): boolean {
    // The post-template's own `layout` attribute is authoritative when
    // present in context — even when it's set to a non-grid value,
    // because the variant lives inside the post-template and inherits
    // its layout. Only fall back to the parent query's `displayLayout`
    // when the post-template hasn't published a layout key at all
    // (older saves, hosts driving grids from the query block).
    const fromPostTemplate = context?.[ 'artisanpack/postTemplateLayout' ];

    if ( typeof fromPostTemplate === 'string' ) {
        return 'grid' === fromPostTemplate;
    }

    return context?.displayLayout?.type === 'grid';
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
    context,
}: PostVariantEditProps ): ReactElement {
    const matcher = getMatcher( attributes );
    const priority = typeof attributes.priority === 'number' ? attributes.priority : 10;
    const label = typeof attributes.label === 'string' ? attributes.label : '';
    const showGridSpans = isGridLayout( context );
    const numColumns = resolveNumColumns( context );
    const gridColumnSpan = clampSpan( attributes.gridColumnSpan, numColumns, 1 );
    const gridRowSpan = clampSpan( attributes.gridRowSpan, MAX_ROW_SPAN, 1 );

    const wrapperClassName = [
        'wp-block-artisanpack-post-variant',
        showGridSpans ? `ap-post-span-${ gridColumnSpan }-base-columns` : '',
        showGridSpans ? `ap-post-span-${ gridRowSpan }-base-row` : '',
    ]
        .filter( ( value: string ) => '' !== value )
        .join( ' ' );

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( {
        className: wrapperClassName,
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
                { showGridSpans && (
                    <PanelBody title={ __( 'Grid Spans', TEXT_DOMAIN ) } initialOpen={ false }>
                        <RangeControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={ __( 'Column Span', TEXT_DOMAIN ) }
                            value={ gridColumnSpan }
                            onChange={ ( value?: number ) =>
                                setAttributes( { gridColumnSpan: clampSpan( value, numColumns, 1 ) } )
                            }
                            min={ 1 }
                            max={ numColumns }
                            allowReset
                            resetFallbackValue={ 1 }
                            help={ __( 'How many grid columns this post spans when it matches this variant.', TEXT_DOMAIN ) }
                        />
                        <RangeControl
                            __nextHasNoMarginBottom
                            __next40pxDefaultSize
                            label={ __( 'Row Span', TEXT_DOMAIN ) }
                            value={ gridRowSpan }
                            onChange={ ( value?: number ) =>
                                setAttributes( { gridRowSpan: clampSpan( value, MAX_ROW_SPAN, 1 ) } )
                            }
                            min={ 1 }
                            max={ MAX_ROW_SPAN }
                            allowReset
                            resetFallbackValue={ 1 }
                            help={ __( 'How many grid rows this post spans when it matches this variant.', TEXT_DOMAIN ) }
                        />
                        { gridRowSpan > ROW_SPAN_WARN_THRESHOLD && (
                            <Notice status="warning" isDismissible={ false }>
                                { __( 'A row span larger than 4 can produce very tall cells. Make sure your layout still makes sense at smaller viewports.', TEXT_DOMAIN ) }
                            </Notice>
                        ) }
                    </PanelBody>
                ) }
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
