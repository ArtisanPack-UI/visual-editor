/**
 * Post title input rendered above the block list.
 *
 * WordPress renders the post title as a large, editable textarea at the
 * top of the canvas — the first thing above the first block. Moving
 * the title out of the top bar (A1, #343) lets hosts give it a more
 * prominent role and mirrors the WordPress post editor.
 *
 * This is deliberately a plain `<textarea>` (not a Gutenberg block) so
 * it stays outside the block-editor store's history, matches the
 * top-bar input's simple semantics, and avoids a new dependency on
 * `@wordpress/editor`. Hosts that want the title to behave like a
 * `core/post-title` block can opt out of this component and insert the
 * block themselves — V2 concern.
 */

import { __ } from '@wordpress/i18n';
import {
    useCallback,
    useEffect,
    useRef,
    type ChangeEvent,
} from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import './post-title.css';

export interface PostTitleProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}

export function PostTitle(props: PostTitleProps): JSX.Element {
    const { value, onChange, placeholder } = props;
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    const handleChange = useCallback(
        (event: ChangeEvent<HTMLTextAreaElement>): void => {
            onChange(event.target.value);
        },
        [onChange]
    );

    // Grow the textarea with its content so long titles wrap cleanly
    // instead of scrolling inside a fixed-height box.
    useEffect(() => {
        const textarea = textareaRef.current;

        if (textarea === null) {
            return;
        }

        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
    }, [value]);

    return (
        <div
            className="ap-visual-editor-post-title"
            data-testid="ap-visual-editor-post-title"
        >
            <label className="ap-visual-editor-post-title__label">
                <span className="screen-reader-text">
                    {__('Title', TEXT_DOMAIN)}
                </span>
                <textarea
                    ref={textareaRef}
                    rows={1}
                    className="ap-visual-editor-post-title__input"
                    value={value}
                    placeholder={placeholder ?? __('Add title', TEXT_DOMAIN)}
                    onChange={handleChange}
                    data-testid="ap-visual-editor-post-title-input"
                />
            </label>
        </div>
    );
}
