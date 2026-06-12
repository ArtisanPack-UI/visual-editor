/**
 * React renderer for the `artisanpack/comments-number` block (#500).
 *
 * Mirrors the Blade partial and the Vue renderer so every environment
 * emits identical markup. The count is server-resolved (PostResolver
 * stamps `_resolvedCommentCount`) and combined with the saved
 * singular / plural labels at render time.
 */

import type { JSX } from 'react';

import { attrInt, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

export function CommentsNumberBlock({
    attributes,
}: BlockRendererProps): JSX.Element {
    const count = Math.max(0, attrInt(attributes._resolvedCommentCount, 0));
    const singular = attrString(attributes.singularCommentText, 'Comment');
    const plural = attrString(attributes.pluralCommentText, 'Comments');
    const label = count === 1 ? singular : plural;
    const className = attrString(attributes.className);

    const classes = classList(['ap-comments-number', className]);
    const line = `${count} ${label}`.trim();

    return <p className={classes}>{line}</p>;
}
