/**
 * Embed — preview surface.
 *
 * Ported from `@wordpress/block-library/src/embed/embed-preview.js`
 * (v9.43.0). The upstream component accepts (and forwards) a number of
 * unused props (caption, onCaptionChange, isSelected, insertBlocksAfter,
 * attributes, setAttributes). Those props are preserved for parity but
 * the fork drops them from the rendered output.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __, sprintf } from '@wordpress/i18n';
import { Placeholder, SandBox } from '@wordpress/components';
import { BlockIcon } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { getAuthority } from '@wordpress/url';

import { getPhotoHtml } from './util';
import WpEmbedPreview from './wp-embed-preview';

interface PreviewData {
    readonly html?: string | false;
    readonly scripts?: unknown;
    readonly url?: string;
    readonly thumbnail_url?: string;
    readonly title?: string;
    readonly [key: string]: unknown;
}

interface EmbedPreviewProps {
    readonly preview: PreviewData;
    readonly previewable: boolean;
    readonly url: string | undefined;
    readonly type: string | undefined;
    readonly isSelected: boolean;
    readonly className: string | undefined;
    readonly icon: unknown;
    readonly label: string;
}

export default function EmbedPreview({
    preview,
    previewable,
    url,
    type,
    isSelected,
    className,
    icon,
    label,
}: EmbedPreviewProps): ReactElement {
    const [interactive, setInteractive] = useState(false);

    if (!isSelected && interactive) {
        // We only want to change this when the block is not selected, because
        // changing it when the block becomes selected makes the overlap
        // disappear too early. Hiding the overlay happens on mouseup when the
        // overlay is clicked.
        setInteractive(false);
    }

    const hideOverlay = (): void => {
        setInteractive(true);
    };

    const { scripts } = preview;

    const html =
        type === 'photo'
            ? getPhotoHtml({
                  url: typeof preview.url === 'string' ? preview.url : undefined,
                  thumbnail_url:
                      typeof preview.thumbnail_url === 'string'
                          ? preview.thumbnail_url
                          : undefined,
                  title:
                      typeof preview.title === 'string'
                          ? preview.title
                          : undefined,
              })
            : typeof preview.html === 'string'
              ? preview.html
              : '';
    const embedSourceUrl = getAuthority(url ?? '');
    const iframeTitle = sprintf(
        // translators: %s: host providing embed content e.g: www.youtube.com
        __('Embedded content from %s'),
        embedSourceUrl ?? ''
    );
    const sandboxClassnames = clsx(
        type,
        className,
        'wp-block-embed__wrapper'
    );

    /* eslint-disable jsx-a11y/no-static-element-interactions */
    const embedWrapper =
        type === 'wp-embed' ? (
            <WpEmbedPreview html={html} />
        ) : (
            <div className="wp-block-embed__wrapper">
                <SandBox
                    html={html}
                    scripts={scripts as string[] | undefined}
                    title={iframeTitle}
                    type={sandboxClassnames}
                    onFocus={hideOverlay}
                />
                {!interactive && (
                    <div
                        className="block-library-embed__interactive-overlay"
                        onMouseUp={hideOverlay}
                    />
                )}
            </div>
        );
    /* eslint-enable jsx-a11y/no-static-element-interactions */

    return (
        <>
            {previewable ? (
                embedWrapper
            ) : (
                <Placeholder
                    icon={<BlockIcon icon={icon} showColors />}
                    label={label}
                >
                    <p className="components-placeholder__error">
                        <a href={url}>{url}</a>
                    </p>
                    <p className="components-placeholder__error">
                        {sprintf(
                            /* translators: %s: host providing embed content e.g: www.youtube.com */
                            __(
                                "Embedded content from %s can't be previewed in the editor."
                            ),
                            embedSourceUrl ?? ''
                        )}
                    </p>
                </Placeholder>
            )}
        </>
    );
}
