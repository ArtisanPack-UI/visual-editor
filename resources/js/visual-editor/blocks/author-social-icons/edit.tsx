/**
 * Author Social Icons — editor-side component (#501).
 *
 * Authors pick which platforms to show, the chip style, the layout
 * direction, and how the chips stretch in their container. The canvas
 * renders a stub preview using the author of whichever post is in scope
 * — server-side `PostResolver` swaps in the real per-post author
 * profile URLs at render time via `_resolvedAuthorSocialLinks`.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    CheckboxControl,
    PanelBody,
    RangeControl,
    SelectControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import {
    authorSocialPlatforms,
    AUTHOR_SOCIAL_PLATFORM_SLUGS,
    type SocialIconDefinition,
} from '../_shared/social-icons';

type IconStyle = 'show-label-icon' | 'show-icon' | 'show-label';
type IconsDirection = 'horizontal' | 'vertical';
type IconsStretch = 'full-width' | 'auto-width';

interface AuthorSocialIconsAttributes {
    readonly socialIcons: ReadonlyArray<string>;
    readonly iconStyle: IconStyle;
    readonly iconsDirection: IconsDirection;
    readonly iconsStretch: IconsStretch;
    readonly iconsBorderRadius: number;
}

interface AuthorSocialIconsEditProps {
    readonly attributes: AuthorSocialIconsAttributes;
    readonly setAttributes: (
        next: Partial<AuthorSocialIconsAttributes>
    ) => void;
}

const ICON_STYLE_OPTIONS: ReadonlyArray<{ label: string; value: IconStyle }> = [
    { label: 'Show icons and labels', value: 'show-label-icon' },
    { label: 'Show icons only', value: 'show-icon' },
    { label: 'Show labels only', value: 'show-label' },
];

const DIRECTION_OPTIONS: ReadonlyArray<{ label: string; value: IconsDirection }> = [
    { label: 'Horizontal', value: 'horizontal' },
    { label: 'Vertical', value: 'vertical' },
];

const STRETCH_OPTIONS: ReadonlyArray<{ label: string; value: IconsStretch }> = [
    { label: 'Full width', value: 'full-width' },
    { label: 'Auto width', value: 'auto-width' },
];

function clampRadius(value: number | undefined): number {
    const parsed =
        typeof value === 'number' && Number.isFinite(value)
            ? Math.trunc(value)
            : 0;
    if (parsed < 0) {
        return 0;
    }
    if (parsed > 50) {
        return 50;
    }
    return parsed;
}

function isIconStyle(value: string): value is IconStyle {
    return value === 'show-label-icon' || value === 'show-icon' || value === 'show-label';
}

function isDirection(value: string): value is IconsDirection {
    return value === 'horizontal' || value === 'vertical';
}

function isStretch(value: string): value is IconsStretch {
    return value === 'full-width' || value === 'auto-width';
}

function isAuthorPlatformSlug(slug: string): boolean {
    return (AUTHOR_SOCIAL_PLATFORM_SLUGS as ReadonlyArray<string>).includes(slug);
}

function togglePlatform(
    list: ReadonlyArray<string>,
    slug: string,
    next: boolean
): string[] {
    const filtered = list.filter((entry) => entry !== slug);
    return next ? [...filtered, slug] : filtered;
}

export default function AuthorSocialIconsEdit({
    attributes,
    setAttributes,
}: AuthorSocialIconsEditProps): ReactElement {
    const selected = Array.isArray(attributes.socialIcons)
        ? attributes.socialIcons.filter(
              (slug): slug is string => typeof slug === 'string' && isAuthorPlatformSlug(slug)
          )
        : [];

    const radius = clampRadius(attributes.iconsBorderRadius);

    const blockProps = useBlockProps({
        className: `ap-author-social-icons ap-author-social-icons--${attributes.iconsDirection} ap-author-social-icons--${attributes.iconsStretch}`,
    });

    const renderChip = (platform: SocialIconDefinition): ReactElement => {
        const showIcon = attributes.iconStyle !== 'show-label';
        const showLabel = attributes.iconStyle !== 'show-icon';

        return (
            <div className="ap-author-social-icons__item" key={platform.slug}>
                <span
                    className={`ap-author-social-icons__chip ${platform.slug}`}
                    style={{ borderRadius: `${radius}px` }}
                >
                    {showIcon && (
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            width="1em"
                            height="1em"
                            aria-hidden="true"
                            focusable="false"
                            className="ap-author-social-icons__icon"
                        >
                            <path d={platform.path} />
                        </svg>
                    )}
                    {showLabel && (
                        <span className="ap-author-social-icons__label">
                            {platform.label}
                        </span>
                    )}
                </span>
            </div>
        );
    };

    return (
        <>
            <InspectorControls>
                <PanelBody
                    title={__('Author social icons settings', TEXT_DOMAIN)}
                    initialOpen
                >
                    {authorSocialPlatforms().map((platform) => (
                        <CheckboxControl
                            key={platform.slug}
                            label={__(platform.label, TEXT_DOMAIN)}
                            checked={selected.includes(platform.slug)}
                            onChange={(next) =>
                                setAttributes({
                                    socialIcons: togglePlatform(
                                        selected,
                                        platform.slug,
                                        next
                                    ),
                                })
                            }
                            __nextHasNoMarginBottom
                        />
                    ))}
                    <SelectControl
                        label={__('Chip style', TEXT_DOMAIN)}
                        value={attributes.iconStyle}
                        options={ICON_STYLE_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (isIconStyle(value)) {
                                setAttributes({ iconStyle: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Direction', TEXT_DOMAIN)}
                        value={attributes.iconsDirection}
                        options={DIRECTION_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (isDirection(value)) {
                                setAttributes({ iconsDirection: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Width', TEXT_DOMAIN)}
                        value={attributes.iconsStretch}
                        options={STRETCH_OPTIONS.map((option) => ({
                            label: __(option.label, TEXT_DOMAIN),
                            value: option.value,
                        }))}
                        onChange={(value) => {
                            if (isStretch(value)) {
                                setAttributes({ iconsStretch: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Border radius', TEXT_DOMAIN)}
                        value={radius}
                        onChange={(value) =>
                            setAttributes({
                                iconsBorderRadius: clampRadius(value),
                            })
                        }
                        min={0}
                        max={50}
                        allowReset
                        resetFallbackValue={0}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {selected.length === 0 ? (
                    <p className="ap-author-social-icons__empty">
                        {__(
                            'Pick at least one platform from the sidebar.',
                            TEXT_DOMAIN
                        )}
                    </p>
                ) : (
                    authorSocialPlatforms()
                        .filter((platform) => selected.includes(platform.slug))
                        .map(renderChip)
                )}
            </div>
        </>
    );
}
