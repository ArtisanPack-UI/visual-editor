/**
 * Breadcrumbs — editor-side preview.
 *
 * Shows a stub trail (Home › Page › Current) so authors get an immediate
 * visual of the chosen separator and schema toggle. The real trail is
 * rendered at runtime by the Blade / React / Vue renderers from the
 * server-stamped `_resolvedTrail` attribute, so the edit component only
 * cares about chrome (separator + wrapper attributes).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import {
    SEPARATOR_ICON_NAMES,
    SeparatorIcon,
    type SeparatorIconName,
} from './separators';

interface BreadcrumbsAttributes {
    readonly separatorIcon: SeparatorIconName;
    readonly breadcrumbsSchema: boolean;
}

interface BreadcrumbsEditProps {
    readonly attributes: BreadcrumbsAttributes;
    readonly setAttributes: (next: Partial<BreadcrumbsAttributes>) => void;
}

const SEPARATOR_LABELS: Readonly<Record<SeparatorIconName, string>> = {
    'arrow-right': 'Arrow Right',
    'chevron-right': 'Chevron Right',
    'chevron-double-right': 'Chevron Double Right',
    'long-arrow-right': 'Long Arrow Right',
};

const SEPARATOR_OPTIONS = SEPARATOR_ICON_NAMES.map((value) => ({
    label: SEPARATOR_LABELS[value],
    value,
}));

function isSeparatorIconName(value: string): value is SeparatorIconName {
    return (SEPARATOR_ICON_NAMES as ReadonlyArray<string>).includes(value);
}

export default function BreadcrumbsEdit({
    attributes,
    setAttributes,
}: BreadcrumbsEditProps): ReactElement {
    const { separatorIcon, breadcrumbsSchema } = attributes;

    const blockProps = useBlockProps({
        className: 'ap-breadcrumbs',
    });

    const stub: ReadonlyArray<string> = [
        __('Home', TEXT_DOMAIN),
        __('Section', TEXT_DOMAIN),
        __('Current Page', TEXT_DOMAIN),
    ];

    // The block's stylesheet is imported as a side effect in index.ts so
    // it lives in the parent document, but the editor's `BlockCanvas`
    // mounts inside a sandboxed iframe that none of those rules reach.
    // Inline the layout-critical rules here so authors get an accurate
    // preview without expanding scope into `canvas-styles.ts`. The
    // sibling `breadcrumbs.css` keeps shipping for the public frontend.
    const listStyle: React.CSSProperties = {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: '0.5rem',
        margin: 0,
        padding: 0,
        listStyle: 'none',
    };
    const itemStyle: React.CSSProperties = {
        display: 'inline-flex',
        alignItems: 'center',
        gap: '0.5rem',
    };
    const separatorStyle: React.CSSProperties = {
        display: 'inline-flex',
        alignItems: 'center',
        opacity: 0.6,
    };

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Breadcrumbs settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Separator icon', TEXT_DOMAIN)}
                        value={separatorIcon}
                        options={SEPARATOR_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (isSeparatorIconName(value)) {
                                setAttributes({ separatorIcon: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Include schema.org markup', TEXT_DOMAIN)}
                        help={__(
                            'Disable if your SEO plugin already emits BreadcrumbList structured data.',
                            TEXT_DOMAIN
                        )}
                        checked={breadcrumbsSchema}
                        onChange={(next) => setAttributes({ breadcrumbsSchema: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <nav {...blockProps} aria-label={__('Breadcrumb', TEXT_DOMAIN)}>
                <ol className="ap-breadcrumbs__list" style={listStyle}>
                    {stub.map((label, index) => {
                        const isLast = index === stub.length - 1;
                        return (
                            <li
                                key={label}
                                className="ap-breadcrumbs__item"
                                style={itemStyle}
                            >
                                <span
                                    className={
                                        isLast
                                            ? 'ap-breadcrumbs__current'
                                            : 'ap-breadcrumbs__link'
                                    }
                                    style={isLast ? { fontWeight: 600 } : undefined}
                                >
                                    {label}
                                </span>
                                {!isLast && (
                                    <span
                                        className="ap-breadcrumbs__separator"
                                        aria-hidden="true"
                                        style={separatorStyle}
                                    >
                                        <SeparatorIcon name={separatorIcon} />
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </nav>
        </>
    );
}
