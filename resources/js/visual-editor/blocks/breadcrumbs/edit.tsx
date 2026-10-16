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

// Built per render so the labels pick up locale data loaded after import.
function separatorLabels(): Readonly<Record<SeparatorIconName, string>> {
    return {
        'arrow-right': __('Arrow Right', TEXT_DOMAIN),
        'chevron-right': __('Chevron Right', TEXT_DOMAIN),
        'chevron-double-right': __('Chevron Double Right', TEXT_DOMAIN),
        'long-arrow-right': __('Long Arrow Right', TEXT_DOMAIN),
    };
}

function separatorOptions(): Array<{ label: string; value: SeparatorIconName }> {
    const labels = separatorLabels();

    return SEPARATOR_ICON_NAMES.map((value) => ({
        label: labels[value],
        value,
    }));
}

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

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Breadcrumbs settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Separator icon', TEXT_DOMAIN)}
                        value={separatorIcon}
                        options={separatorOptions()}
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
                <ol className="ap-breadcrumbs__list">
                    {stub.map((label, index) => {
                        const isLast = index === stub.length - 1;
                        return (
                            <li key={label} className="ap-breadcrumbs__item">
                                <span
                                    className={
                                        isLast
                                            ? 'ap-breadcrumbs__current'
                                            : 'ap-breadcrumbs__link'
                                    }
                                >
                                    {label}
                                </span>
                                {!isLast && (
                                    <span
                                        className="ap-breadcrumbs__separator"
                                        aria-hidden="true"
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
