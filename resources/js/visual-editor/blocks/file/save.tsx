/**
 * File — save (front-end serialization).
 *
 * Ported from `@wordpress/block-library/src/file/save.js` (v9.43.0).
 * Behaviour is byte-equivalent to upstream — the only change is the
 * namespace swap to `artisanpack/file`. Mixed documents containing
 * `core/file` and `artisanpack/file` render to identical HTML.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import {
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';

interface FileAttributes {
    readonly href?: string;
    readonly fileId?: string;
    readonly fileName?: string;
    readonly textLinkHref?: string;
    readonly textLinkTarget?: string;
    readonly showDownloadButton?: boolean;
    readonly downloadButtonText?: string;
    readonly displayPreview?: boolean;
    readonly previewHeight?: number;
}

interface FileSaveProps {
    readonly attributes: FileAttributes;
}

export default function save({ attributes }: FileSaveProps): ReactElement | null {
    const {
        href,
        fileId,
        fileName,
        textLinkHref,
        textLinkTarget,
        showDownloadButton,
        downloadButtonText,
        displayPreview,
        previewHeight,
    } = attributes;

    if (!href) {
        return null;
    }

    const pdfEmbedLabel = RichText.isEmpty(fileName ?? '')
        ? 'PDF embed'
        : // To do: use toPlainText, but we need ensure it's RichTextData. See
          // https://github.com/WordPress/gutenberg/pull/56710.
          (fileName as unknown as { toString(): string }).toString();

    const hasFilename = !RichText.isEmpty(fileName ?? '');

    // Only output an `aria-describedby` when the element it's referring to is
    // actually rendered.
    const describedById = hasFilename ? fileId : undefined;

    return (
        <div {...useBlockProps.save()}>
            {displayPreview && (
                <>
                    <object
                        className="wp-block-file__embed"
                        data={href}
                        type="application/pdf"
                        style={{
                            width: '100%',
                            height: `${previewHeight}px`,
                        }}
                        aria-label={pdfEmbedLabel}
                    />
                </>
            )}
            {hasFilename && (
                <a
                    id={describedById}
                    href={textLinkHref}
                    target={textLinkTarget}
                    rel={textLinkTarget ? 'noreferrer noopener' : undefined}
                >
                    <RichText.Content value={fileName} />
                </a>
            )}
            {showDownloadButton && (
                <a
                    href={href}
                    className={clsx(
                        'wp-block-file__button',
                        __experimentalGetElementClassName('button')
                    )}
                    download
                    aria-describedby={describedById}
                >
                    <RichText.Content value={downloadButtonText} />
                </a>
            )}
        </div>
    );
}
