/**
 * File — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/file/edit.js` (v9.43.0).
 * Behaviour parity is the goal for everything the saved markup depends on:
 * the `href`/`fileName`/`textLinkHref`/`textLinkTarget`/`showDownloadButton`/
 * `downloadButtonText`/`displayPreview`/`previewHeight` attributes round-trip
 * losslessly across editor sessions. Intentional divergences (documented in
 * `upstream-state.json` under `knownDivergences`):
 *
 *   - The blob-URL upload path (`useUploadMediaFromBlobURL`) is omitted.
 *     Files dropped onto the placeholder are uploaded via `MediaPlaceholder`
 *     or `MediaReplaceFlow` rather than eagerly mounted from a blob URL.
 *   - The clipboard toolbar button (`ClipboardToolbarButton` /
 *     `useCopyToClipboard`) is dropped to avoid a dependency on
 *     `@wordpress/compose` clipboard internals.
 *   - The PDF preview ResizableBox / inspector PDF settings panel is dropped.
 *     `displayPreview` and `previewHeight` are still preserved on the
 *     attribute schema and saved verbatim, but the editor preview is a
 *     plain `<object>` without resize handles.
 *   - The `FileBlockInspector` link-destination / open-in-new-tab /
 *     show-download-button controls are inlined directly into this
 *     component as a `ToolsPanel` so the fork doesn't depend on the
 *     upstream `useToolsPanelDropdownMenuProps` hook from
 *     `@wordpress/block-library/src/utils/hooks`.
 *   - The shared `removeAnchorTag` helper from
 *     `@wordpress/block-library/src/utils/remove-anchor-tag` is inlined as
 *     a small local function for the same reason.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { isBlobURL } from '@wordpress/blob';
import {
    SelectControl,
    ToggleControl,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import {
    BlockControls,
    BlockIcon,
    InspectorControls,
    MediaPlaceholder,
    MediaReplaceFlow,
    RichText,
    useBlockProps,
    __experimentalGetElementClassName,
} from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';
import { file as icon } from '@wordpress/icons';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import { getFilename } from '@wordpress/url';

interface FileAttributes {
    readonly id?: number;
    readonly blob?: string;
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

interface MediaItem {
    readonly id?: number;
    readonly url?: string;
    readonly title?: string;
    readonly mime?: string;
    readonly mime_type?: string;
}

interface AttachmentRecord {
    readonly link?: string;
}

interface FileEditProps {
    readonly attributes: FileAttributes;
    readonly className?: string;
    readonly isSelected: boolean;
    readonly setAttributes: (next: Partial<FileAttributes>) => void;
    readonly clientId: string;
}

/**
 * Strip a leading `<a …>` open tag and matching close tag from a string so
 * that RichText edits don't inject nested anchors.
 */
function removeAnchorTag(value: string): string {
    return value.toString().replace(/<\/?a[^>]*>/g, '');
}

export default function FileEdit({
    attributes,
    isSelected,
    setAttributes,
    clientId,
}: FileEditProps): ReactElement {
    const {
        id,
        fileName,
        href,
        textLinkHref,
        textLinkTarget,
        showDownloadButton,
        downloadButtonText,
    } = attributes;

    const media = useSelect(
        (select) =>
            id === undefined
                ? undefined
                : (select(coreStore).getEntityRecord(
                      'postType',
                      'attachment',
                      id
                  ) as AttachmentRecord | undefined),
        [id]
    );

    const { createErrorNotice } = useDispatch(noticesStore);

    // Note: Handle setting a default value for `downloadButtonText` via HTML API
    // when it supports replacing text content for HTML tags.
    useEffect(() => {
        if (RichText.isEmpty(downloadButtonText ?? '')) {
            setAttributes({
                downloadButtonText: _x('Download', 'button label'),
            });
        }
        // This effect should only run on mount.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function onSelectFile(newMedia: MediaItem | undefined): void {
        if (!newMedia || !newMedia.url) {
            setAttributes({
                href: undefined,
                fileName: undefined,
                textLinkHref: undefined,
                id: undefined,
                fileId: undefined,
                displayPreview: undefined,
                previewHeight: undefined,
            });
            return;
        }

        if (isBlobURL(newMedia.url)) {
            return;
        }

        const isPdf =
            (newMedia.mime || newMedia.mime_type) === 'application/pdf' ||
            getFilename(newMedia.url).toLowerCase().endsWith('.pdf');
        const pdfAttributes: Partial<FileAttributes> = {
            displayPreview: isPdf
                ? attributes.displayPreview ?? true
                : undefined,
            previewHeight: isPdf ? attributes.previewHeight ?? 600 : undefined,
        };

        setAttributes({
            href: newMedia.url,
            fileName: newMedia.title,
            textLinkHref: newMedia.url,
            id: newMedia.id,
            fileId: `wp-block-file--media-${clientId}`,
            blob: undefined,
            ...pdfAttributes,
        });
    }

    function onUploadError(message: string): void {
        setAttributes({ href: undefined });
        createErrorNotice(message, { type: 'snackbar' });
    }

    function changeLinkDestinationOption(newHref: string): void {
        setAttributes({ textLinkHref: newHref });
    }

    function changeOpenInNewWindow(newValue: boolean): void {
        setAttributes({
            textLinkTarget: newValue ? '_blank' : undefined,
        });
    }

    function changeShowDownloadButton(newValue: boolean): void {
        setAttributes({ showDownloadButton: newValue });
    }

    const attachmentPage = media && media.link;

    const blockProps = useBlockProps({
        className: clsx(),
    });

    if (!href) {
        return (
            <div {...blockProps}>
                <MediaPlaceholder
                    icon={<BlockIcon icon={icon} />}
                    labels={{
                        title: __('File'),
                        instructions: __(
                            'Drag and drop a file, upload, or choose from your library.'
                        ),
                    }}
                    onSelect={onSelectFile}
                    onError={onUploadError}
                    accept="*"
                />
            </div>
        );
    }

    const linkDestinationOptions = attachmentPage
        ? [
              { value: href, label: __('Media file') },
              { value: attachmentPage, label: __('Attachment page') },
          ]
        : [{ value: href, label: __('URL') }];

    const openInNewWindow = !!textLinkTarget;

    return (
        <>
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings')}
                    resetAll={() => {
                        changeLinkDestinationOption(href);
                        changeOpenInNewWindow(false);
                        changeShowDownloadButton(true);
                    }}
                >
                    <ToolsPanelItem
                        label={__('Link to')}
                        isShownByDefault
                        hasValue={() => textLinkHref !== href}
                        onDeselect={() => changeLinkDestinationOption(href)}
                    >
                        <SelectControl
                            __next40pxDefaultSize
                            label={__('Link to')}
                            value={textLinkHref ?? ''}
                            options={linkDestinationOptions}
                            onChange={changeLinkDestinationOption}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Open in new tab')}
                        isShownByDefault
                        hasValue={() => !!openInNewWindow}
                        onDeselect={() => changeOpenInNewWindow(false)}
                    >
                        <ToggleControl
                            label={__('Open in new tab')}
                            checked={openInNewWindow}
                            onChange={changeOpenInNewWindow}
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={__('Show download button')}
                        isShownByDefault
                        hasValue={() => !showDownloadButton}
                        onDeselect={() => changeShowDownloadButton(true)}
                    >
                        <ToggleControl
                            label={__('Show download button')}
                            checked={!!showDownloadButton}
                            onChange={changeShowDownloadButton}
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            {isSelected && (
                <BlockControls group="other">
                    <MediaReplaceFlow
                        mediaId={id}
                        mediaURL={href}
                        accept="*"
                        onSelect={onSelectFile}
                        onError={onUploadError}
                        onReset={() => onSelectFile(undefined)}
                    />
                </BlockControls>
            )}
            <div {...blockProps}>
                <div className="wp-block-file__content-wrapper">
                    <RichText
                        identifier="fileName"
                        tagName="a"
                        value={fileName ?? ''}
                        placeholder={__('Write file name…')}
                        withoutInteractiveFormatting
                        onChange={(text: string) =>
                            setAttributes({
                                fileName: removeAnchorTag(text),
                            })
                        }
                        href={textLinkHref}
                    />
                    {showDownloadButton && (
                        <div className="wp-block-file__button-richtext-wrapper">
                            {/* Using RichText here instead of PlainText so that it can be styled like a button. */}
                            <RichText
                                identifier="downloadButtonText"
                                tagName="div"
                                aria-label={__('Download button text')}
                                className={clsx(
                                    'wp-block-file__button',
                                    __experimentalGetElementClassName('button')
                                )}
                                value={downloadButtonText ?? ''}
                                withoutInteractiveFormatting
                                placeholder={__('Add text…')}
                                onChange={(text: string) =>
                                    setAttributes({
                                        downloadButtonText:
                                            removeAnchorTag(text),
                                    })
                                }
                            />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
