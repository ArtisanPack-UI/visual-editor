/**
 * ArtisanPackLinkControl — `LinkControl` with a Dynamic Content tab (#662).
 *
 * The core `__experimentalLinkControl` popover (used by the Button block
 * and the RichText link format) has no seam for adding a "pick a Dynamic
 * Content field" step. This wrapper composes the stock control with a
 * second tab — `DynamicContentLinkPicker` — that lists the Dynamic
 * Content URL / email / phone / address fields exposed by
 * `GET /visual-editor/api/dynamic-content/sources`.
 *
 * The field-picker and the href helpers live in `./dynamic-link-picker`
 * so surfaces that must not pull the block-editor bundle (the site-editor
 * Navigation link picker) can reuse them without importing
 * `@wordpress/block-editor`.
 *
 * @since 1.7.0
 */

import {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalLinkControl as LinkControl,
} from '@wordpress/block-editor';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { DynamicContentLinkPicker } from './dynamic-link-picker';

export {
    DynamicContentLinkPicker,
    DC_LINK_FIELD_TYPES,
    schemeForFieldType,
    buildDynamicContentHref,
    type DynamicLinkFieldRow,
} from './dynamic-link-picker';

export interface LinkControlValue {
    url?: string;
    opensInNewTab?: boolean;
    [key: string]: unknown;
}

export interface ArtisanPackLinkControlProps {
    value?: LinkControlValue;
    onChange: (next: LinkControlValue) => void;
    onRemove?: () => void;
    forceIsEditingLink?: boolean;
    /** Passthrough for the stock control's `settings` (e.g. "open in new tab"). */
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    settings?: any;
}

/**
 * `LinkControl` composed with a Dynamic Content tab. Drop-in wherever the
 * bare `__experimentalLinkControl` is used.
 *
 * @since 1.7.0
 */
export function ArtisanPackLinkControl(props: ArtisanPackLinkControlProps): JSX.Element {
    const { value, onChange, onRemove, forceIsEditingLink, settings } = props;

    return (
        <div className="ap-dc-link-control" style={{ minWidth: 320 }}>
            <TabPanel
                className="ap-dc-link-control__tabs"
                tabs={[
                    { name: 'link', title: __('Link', 'artisanpack-visual-editor') },
                    { name: 'dynamic', title: __('Dynamic Content', 'artisanpack-visual-editor') },
                ]}
            >
                {(tab) =>
                    tab.name === 'dynamic' ? (
                        <DynamicContentLinkPicker
                            onSelect={(href) => onChange({ ...(value ?? {}), url: href })}
                        />
                    ) : (
                        // eslint-disable-next-line @typescript-eslint/no-explicit-any
                        <LinkControl
                            value={value}
                            onChange={onChange}
                            onRemove={onRemove}
                            forceIsEditingLink={forceIsEditingLink}
                            settings={settings}
                        />
                    )
                }
            </TabPanel>
        </div>
    );
}

export default ArtisanPackLinkControl;
