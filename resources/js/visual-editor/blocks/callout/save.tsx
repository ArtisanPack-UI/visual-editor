/**
 * Callout — saved markup.
 *
 * Persists the same DOM shape the edit component renders so Gutenberg's
 * save-vs-edit validation passes on reload. The frontend renderers
 * (Blade, React, Vue) output the serialized HTML verbatim; the host
 * application is responsible for shipping the matching `.ap-callout`
 * stylesheet.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

import { CalloutIcon, type CalloutIconName } from './icons';

type CalloutSeverity = 'info' | 'success' | 'warning' | 'error';

interface CalloutAttributes {
    readonly severity: CalloutSeverity;
    readonly icon: CalloutIconName;
    readonly content: string;
}

interface CalloutSaveProps {
    readonly attributes: CalloutAttributes;
}

export default function CalloutSave({
    attributes,
}: CalloutSaveProps): ReactElement {
    const { severity, icon, content } = attributes;

    const blockProps = useBlockProps.save({
        className: `ap-callout ap-callout--${severity}`,
        'data-severity': severity,
    });

    return (
        <div {...blockProps}>
            <span className="ap-callout__icon" aria-hidden="true">
                <CalloutIcon name={icon} />
            </span>
            <RichText.Content
                tagName="div"
                className="ap-callout__body"
                value={content}
            />
        </div>
    );
}
