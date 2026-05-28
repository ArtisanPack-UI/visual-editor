/**
 * Embed — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/embed/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the `url`/`caption`/`type`/`providerNameSlug`/`allowResponsive`/
 * `responsive`/`previewable` attributes round-trip losslessly across
 * editor sessions. Intentional divergences (documented in
 * `upstream-state.json` under `knownDivergences`):
 *
 *   - The upstream `Caption` component from
 *     `@wordpress/block-library/src/utils/caption` is replaced by an
 *     inline RichText figcaption so the fork doesn't depend on
 *     block-library internals not exposed in the package's `exports`.
 *   - The upstream `View` primitive from `@wordpress/primitives` is
 *     replaced with a plain `<div>`; behaviour is identical on web.
 */

import type { ReactElement, FormEvent } from 'react';
import clsx from 'clsx';
import { __, _x, sprintf } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
    RichText,
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';
import { store as coreStore } from '@wordpress/core-data';
import { getAuthority } from '@wordpress/url';

import {
    createUpgradedEmbedBlock,
    getClassNames,
    removeAspectRatioClasses,
    fallback,
    getEmbedInfoByProvider,
    getMergedAttributesWithPreview,
} from './util';
import EmbedControls from './embed-controls';
import { embedContentIcon } from './icons';
import EmbedLoading from './embed-loading';
import EmbedPlaceholder from './embed-placeholder';
import EmbedPreview from './embed-preview';

interface EmbedAttributes {
    readonly url?: string;
    readonly caption?: string;
    readonly type?: string;
    readonly providerNameSlug?: string;
    readonly allowResponsive?: boolean;
    readonly responsive?: boolean;
    readonly previewable?: boolean;
    readonly className?: string;
    readonly [key: string]: unknown;
}

interface EmbedEditProps {
    readonly attributes: EmbedAttributes;
    readonly setAttributes: (next: Partial<EmbedAttributes>) => void;
    readonly className?: string;
    readonly isSelected: boolean;
    readonly onReplace?: (block: unknown) => void;
    readonly insertBlocksAfter?: (block: unknown) => void;
    readonly onFocus?: () => void;
}

interface PreviewData {
    html?: string | false;
    type?: string;
    data?: { status?: number };
    [key: string]: unknown;
}

const EmbedEdit = (props: EmbedEditProps): ReactElement => {
    const {
        attributes: {
            providerNameSlug,
            previewable,
            responsive,
            url: attributesUrl,
        },
        attributes,
        isSelected,
        onReplace,
        setAttributes,
        onFocus,
    } = props;

    const defaultEmbedInfo = {
        title: _x('Embed', 'block title'),
        icon: embedContentIcon,
    };
    const variation = getEmbedInfoByProvider(providerNameSlug);
    const icon =
        (variation as { icon?: unknown } | undefined)?.icon ??
        defaultEmbedInfo.icon;
    const title =
        (variation as { title?: string } | undefined)?.title ??
        defaultEmbedInfo.title;

    const [url, setURL] = useState(attributesUrl);
    const [isEditingURL, setIsEditingURL] = useState(false);
    const { invalidateResolution } = useDispatch(coreStore);

    const {
        preview,
        fetching,
        themeSupportsResponsive,
        cannotEmbed,
        hasResolved,
    } = useSelect(
        (select) => {
            const {
                getEmbedPreview,
                isPreviewEmbedFallback,
                isRequestingEmbedPreview,
                getThemeSupports,
                hasFinishedResolution,
            } = select(coreStore) as {
                getEmbedPreview: (url: string) => PreviewData | undefined;
                isPreviewEmbedFallback: (url: string) => boolean;
                isRequestingEmbedPreview: (url: string) => boolean;
                getThemeSupports: () => Record<string, unknown>;
                hasFinishedResolution: (
                    selector: string,
                    args: unknown[]
                ) => boolean;
            };
            if (!attributesUrl) {
                return {
                    fetching: false,
                    cannotEmbed: false,
                    preview: undefined,
                    themeSupportsResponsive: false,
                    hasResolved: false,
                };
            }

            const embedPreview = getEmbedPreview(attributesUrl);
            const previewIsFallback = isPreviewEmbedFallback(attributesUrl);

            const badEmbedProvider =
                embedPreview?.html === false &&
                embedPreview?.type === undefined;
            const wordpressCantEmbed = embedPreview?.data?.status === 404;
            const validPreview =
                !!embedPreview && !badEmbedProvider && !wordpressCantEmbed;

            return {
                preview: validPreview ? embedPreview : undefined,
                fetching: isRequestingEmbedPreview(attributesUrl),
                themeSupportsResponsive:
                    !!getThemeSupports()['responsive-embeds'],
                cannotEmbed: !validPreview || previewIsFallback,
                hasResolved: hasFinishedResolution('getEmbedPreview', [
                    attributesUrl,
                ]),
            };
        },
        [attributesUrl]
    );

    const getMergedAttributes = (): EmbedAttributes =>
        getMergedAttributesWithPreview(
            attributes,
            preview,
            title,
            !!responsive
        );

    function toggleResponsive(newAllowResponsive: boolean): void {
        const { className } = attributes;
        const html = (preview as PreviewData | undefined)?.html;
        setAttributes({
            allowResponsive: newAllowResponsive,
            className: getClassNames(
                typeof html === 'string' ? html : '',
                className,
                !!responsive && newAllowResponsive
            ),
        });
    }

    useEffect(() => {
        if (preview?.html || !cannotEmbed || !hasResolved) {
            return;
        }

        // At this stage, we're not fetching the preview and know it can't be
        // embedded, so try removing any trailing slash, and resubmit.
        const newURL = (attributesUrl ?? '').replace(/\/$/, '');
        setURL(newURL);
        setIsEditingURL(false);
        setAttributes({ url: newURL });
    }, [
        preview?.html,
        attributesUrl,
        cannotEmbed,
        hasResolved,
        setAttributes,
    ]);

    // Try a different provider in case the embed url is not supported.
    useEffect(() => {
        if (!cannotEmbed || fetching || !url) {
            return;
        }

        // Until X provider is supported in WordPress, as a workaround we use
        // Twitter provider.
        if (getAuthority(url) === 'x.com') {
            const newURL = new URL(url);
            newURL.host = 'twitter.com';
            setAttributes({ url: newURL.toString() });
        }
    }, [url, cannotEmbed, fetching, setAttributes]);

    // Handle incoming preview.
    useEffect(() => {
        if (preview && !isEditingURL) {
            const mergedAttributes = getMergedAttributes();
            const hasChanges = Object.keys(mergedAttributes).some(
                (key) =>
                    (mergedAttributes as Record<string, unknown>)[key] !==
                    (attributes as Record<string, unknown>)[key]
            );

            if (hasChanges) {
                setAttributes(mergedAttributes);
            }

            if (onReplace) {
                const upgradedBlock = createUpgradedEmbedBlock(
                    { preview, attributes },
                    mergedAttributes
                );

                if (upgradedBlock) {
                    onReplace(upgradedBlock);
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [preview, isEditingURL]);

    const blockProps = useBlockProps();

    if (fetching) {
        return (
            <div {...blockProps}>
                <EmbedLoading />
            </div>
        );
    }

    // translators: %s: type of embed e.g: "YouTube", "Twitter", etc.
    const label = sprintf(__('%s URL'), title);

    const showEmbedPlaceholder = !preview || cannotEmbed || isEditingURL;

    if (showEmbedPlaceholder) {
        return (
            <div {...blockProps}>
                <EmbedPlaceholder
                    icon={icon}
                    label={label}
                    onFocus={onFocus as undefined}
                    onSubmit={(event?: FormEvent<HTMLFormElement>) => {
                        if (event) {
                            event.preventDefault();
                        }

                        const blockClass = removeAspectRatioClasses(
                            attributes.className
                        );

                        setIsEditingURL(false);
                        setAttributes({ url, className: blockClass });
                    }}
                    value={url}
                    cannotEmbed={cannotEmbed}
                    onChange={(value) => setURL(value)}
                    fallback={() =>
                        fallback(
                            url ?? '',
                            onReplace as (block: unknown) => void
                        )
                    }
                    tryAgain={() => {
                        invalidateResolution('getEmbedPreview', [url]);
                    }}
                />
            </div>
        );
    }

    const {
        caption,
        type,
        className: classFromPreview,
    } = getMergedAttributes();
    const className = clsx(classFromPreview, props.className);
    const hasCaption = !RichText.isEmpty(caption ?? '');

    return (
        <>
            <EmbedControls
                showEditButton={!!preview && !cannotEmbed}
                themeSupportsResponsive={themeSupportsResponsive}
                blockSupportsResponsive={!!responsive}
                allowResponsive={attributes.allowResponsive ?? true}
                toggleResponsive={toggleResponsive}
                switchBackToURLInput={() => setIsEditingURL(true)}
            />
            <figure
                {...blockProps}
                className={clsx(blockProps.className, className, {
                    [`is-type-${type}`]: type,
                    [`is-provider-${providerNameSlug}`]: providerNameSlug,
                    [`wp-block-embed-${providerNameSlug}`]: providerNameSlug,
                })}
            >
                <EmbedPreview
                    preview={preview as PreviewData}
                    previewable={previewable ?? true}
                    className={className}
                    url={url}
                    type={type}
                    isSelected={isSelected}
                    icon={icon}
                    label={label}
                />
                {(isSelected || hasCaption) && (
                    <RichText
                        identifier="caption"
                        tagName="figcaption"
                        aria-label={__('Embed caption text')}
                        placeholder={__('Add caption')}
                        value={caption ?? ''}
                        onChange={(value: string) =>
                            setAttributes({ caption: value })
                        }
                        inlineToolbar
                    />
                )}
            </figure>
        </>
    );
};

export default EmbedEdit;

// Convenience: also export InspectorControls so consumers tree-shake correctly.
export { InspectorControls };
