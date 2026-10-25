/**
 * Social Icon — editor-side render (#1023).
 *
 * Ported from `@wordpress/block-library/src/social-link/edit.js`
 * (v9.43.0). The `<li>` carries the same `wp-social-link` /
 * `wp-social-link-{service}` classes as WordPress so the bundled
 * block-library CSS (canvas and front end) styles it unchanged, and the
 * button inside it opens a URL popover on click or Enter.
 *
 * Divergences from upstream:
 *
 *   - The service name and glyph come from the generated `./services`
 *     registry instead of `getActiveBlockVariation()`, so an unknown
 *     service falls back to the generic link glyph without a store read.
 *   - `useToolsPanelDropdownMenuProps` is omitted — it is not reachable
 *     through the block-library `exports` field.
 *   - The parent's settings arrive under `artisanpack/socialLinks*`
 *     context keys rather than upstream's bare `openInNewTab` /
 *     `showLabels` so they cannot collide with another block's.
 *   - The icon colours are not read from context or set inline on the
 *     `<li>`: the parent applies them, with its border and hover styles,
 *     through `--ap-social-links-*` properties and `social-links.css`.
 */

import clsx from 'clsx';
import type { KeyboardEvent, ReactElement } from 'react';
import {
    BlockControls,
    InspectorControls,
    URLInput,
    URLPopover,
    useBlockEditingMode,
    useBlockProps,
    store as blockEditorStore,
} from '@wordpress/block-editor';
import {
    Button,
    Dropdown,
    ExternalLink,
    TextControl,
    ToolbarButton,
    __experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useMergeRefs } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import { createInterpolateElement, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { keyboardReturn } from '@wordpress/icons';
import { BACKSPACE, DELETE, ENTER } from '@wordpress/keycodes';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import ServiceIcon from './service-icon';
import { getSocialLinkService } from './utils';

export interface SocialLinkAttributes {
    readonly url?: string;
    readonly service?: string;
    readonly label?: string;
    readonly rel?: string;
}

export interface SocialLinkContext {
    readonly 'artisanpack/socialLinksShowLabels'?: boolean;
}

interface SocialLinkEditProps {
    readonly attributes: SocialLinkAttributes;
    readonly context: SocialLinkContext;
    readonly isSelected: boolean;
    readonly setAttributes: (next: Partial<SocialLinkAttributes>) => void;
    readonly clientId: string;
}

interface SocialLinkURLPopoverProps {
    readonly url?: string;
    readonly setAttributes: (next: Partial<SocialLinkAttributes>) => void;
    readonly setPopover: (open: boolean) => void;
    readonly popoverAnchor: HTMLElement | null;
    readonly clientId: string;
}

function SocialLinkURLPopover({
    url,
    setAttributes,
    setPopover,
    popoverAnchor,
    clientId,
}: SocialLinkURLPopoverProps): ReactElement {
    const { removeBlock } = useDispatch(blockEditorStore);

    const close = (): void => {
        setPopover(false);
        popoverAnchor?.focus();
    };

    return (
        <URLPopover
            anchor={popoverAnchor}
            aria-label={__('Edit social link', TEXT_DOMAIN)}
            onClose={close}
        >
            <form
                className="block-editor-url-popover__link-editor"
                onSubmit={(event) => {
                    event.preventDefault();
                    close();
                }}
            >
                <div className="block-editor-url-input">
                    <URLInput
                        value={url}
                        onChange={(nextURL: string) => setAttributes({ url: nextURL })}
                        placeholder={__('Enter social link', TEXT_DOMAIN)}
                        label={__('Enter social link', TEXT_DOMAIN)}
                        hideLabelFromVision
                        disableSuggestions
                        onKeyDown={(event: KeyboardEvent) => {
                            // An empty link removed with Backspace / Delete
                            // removes the icon, as upstream does.
                            if (
                                !!url ||
                                event.defaultPrevented ||
                                ![BACKSPACE, DELETE].includes(event.keyCode)
                            ) {
                                return;
                            }
                            removeBlock(clientId);
                        }}
                        suffix={
                            <InputControlSuffixWrapper variant="control">
                                <Button
                                    icon={keyboardReturn}
                                    label={__('Apply', TEXT_DOMAIN)}
                                    type="submit"
                                    size="small"
                                />
                            </InputControlSuffixWrapper>
                        }
                    />
                </div>
            </form>
        </URLPopover>
    );
}

export default function SocialLinkEdit({
    attributes,
    context,
    isSelected,
    setAttributes,
    clientId,
}: SocialLinkEditProps): ReactElement {
    const { url, service = '', label = '', rel } = attributes;
    const showLabels = !!context['artisanpack/socialLinksShowLabels'];

    const [showURLPopover, setPopover] = useState(false);
    const [popoverAnchor, setPopoverAnchor] = useState<HTMLElement | null>(null);
    const ref = useRef<HTMLElement | null>(null);
    const isContentOnlyMode = useBlockEditingMode() === 'contentOnly';

    // Same slug check and `chain` fallback as the three renderers, so the
    // canvas and the front end carry the same service class.
    const serviceSlug = /^[a-z0-9-]+$/.test(service) ? service : '';
    const serviceInfo = getSocialLinkService(service);
    const socialLinkName = serviceInfo?.label ?? __('Social Icon', TEXT_DOMAIN);
    const socialLinkText = label.trim() === '' ? socialLinkName : label;

    const wrapperClasses = clsx(
        'wp-social-link',
        // Kept on the `<li>` for upstream CSS compatibility after block
        // props moved to the button (WordPress/gutenberg#64883).
        'wp-block-social-link',
        `wp-social-link-${'' !== serviceSlug ? serviceSlug : 'chain'}`,
        {
            'wp-social-link__is-incomplete': !url,
        }
    );

    const blockProps = useBlockProps({
        className: 'wp-block-social-link-anchor',
        ref: useMergeRefs([setPopoverAnchor, ref]),
        onClick: () => setPopover(true),
        onKeyDown: (event: KeyboardEvent) => {
            if (event.keyCode === ENTER) {
                event.preventDefault();
                setPopover(true);
            }
        },
    });

    return (
        <>
            {isContentOnlyMode && showLabels && (
                // The inspector is hidden in content-only mode, so the label
                // needs a toolbar control there.
                <BlockControls group="other">
                    <Dropdown
                        popoverProps={{ placement: 'bottom-start' }}
                        renderToggle={({ isOpen, onToggle }) => (
                            <ToolbarButton
                                onClick={onToggle}
                                aria-haspopup="true"
                                aria-expanded={isOpen}
                            >
                                {__('Text', TEXT_DOMAIN)}
                            </ToolbarButton>
                        )}
                        renderContent={() => (
                            <TextControl
                                __next40pxDefaultSize
                                __nextHasNoMarginBottom
                                className="wp-block-social-link__toolbar_content_text"
                                label={__('Text', TEXT_DOMAIN)}
                                help={__('Provide a text label or use the default.', TEXT_DOMAIN)}
                                value={label}
                                onChange={(value: string) => setAttributes({ label: value })}
                                placeholder={socialLinkName}
                            />
                        )}
                    />
                </BlockControls>
            )}
            <InspectorControls>
                <ToolsPanel
                    label={__('Settings', TEXT_DOMAIN)}
                    resetAll={() => setAttributes({ label: undefined })}
                >
                    <ToolsPanelItem
                        isShownByDefault
                        label={__('Text', TEXT_DOMAIN)}
                        hasValue={() => !!label}
                        onDeselect={() => setAttributes({ label: undefined })}
                    >
                        <TextControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={__('Text', TEXT_DOMAIN)}
                            help={__(
                                'The text is visible when enabled from the parent Social Icons block.',
                                TEXT_DOMAIN
                            )}
                            value={label}
                            onChange={(value: string) => setAttributes({ label: value })}
                            placeholder={socialLinkName}
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <InspectorControls group="advanced">
                <TextControl
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                    label={__('Link relation', TEXT_DOMAIN)}
                    help={createInterpolateElement(
                        __(
                            'The <a>Link Relation</a> attribute defines the relationship between a linked resource and the current document.',
                            TEXT_DOMAIN
                        ),
                        {
                            // @ts-expect-error - children are supplied by createInterpolateElement
                            a: <ExternalLink href="https://developer.mozilla.org/docs/Web/HTML/Attributes/rel" />,
                        }
                    )}
                    value={rel || ''}
                    onChange={(value: string) => setAttributes({ rel: value })}
                />
            </InspectorControls>
            <li role="presentation" className={wrapperClasses}>
                <button aria-haspopup="dialog" {...blockProps} role="button">
                    <ServiceIcon service={service} />
                    <span
                        className={clsx('wp-block-social-link-label', {
                            'screen-reader-text': !showLabels,
                        })}
                    >
                        {socialLinkText}
                    </span>
                </button>
                {isSelected && showURLPopover && (
                    <SocialLinkURLPopover
                        url={url}
                        setAttributes={setAttributes}
                        setPopover={setPopover}
                        popoverAnchor={popoverAnchor}
                        clientId={clientId}
                    />
                )}
            </li>
        </>
    );
}
