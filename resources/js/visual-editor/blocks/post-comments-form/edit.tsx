/**
 * PostCommentsForm — edit component.
 *
 * Server-rendered interactive block. The real comment form is emitted
 * by the front-end renderer from `_resolved*` attributes; the editor
 * preview renders a representative, non-interactive form so authors
 * can style every label, input, and submit-button state in the
 * canvas. Comments family fork (#519) Pass 2.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const wrapperStyle: CSSProperties = {
    pointerEvents: 'none',
};

export default function PostCommentsFormEdit(): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )( {
        className: 'wp-block-post-comments-form comment-respond',
        style: wrapperStyle,
    } );

    return (
        <div { ...blockProps }>
            <h3 className="comment-reply-title">
                { __( 'Leave a Comment', TEXT_DOMAIN ) }
            </h3>
            <form className="comment-form" onSubmit={ ( e ) => e.preventDefault() }>
                <p className="comment-form-author">
                    <label>
                        { __( 'Name', TEXT_DOMAIN ) }{ ' ' }
                        <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" disabled />
                </p>
                <p className="comment-form-email">
                    <label>
                        { __( 'Email', TEXT_DOMAIN ) }{ ' ' }
                        <span aria-hidden="true">*</span>
                    </label>
                    <input type="email" disabled />
                </p>
                <p className="comment-form-url">
                    <label>{ __( 'Website', TEXT_DOMAIN ) }</label>
                    <input type="url" disabled />
                </p>
                <p className="comment-form-comment">
                    <label>
                        { __( 'Comment', TEXT_DOMAIN ) }{ ' ' }
                        <span aria-hidden="true">*</span>
                    </label>
                    <textarea rows={ 6 } disabled />
                </p>
                <p className="form-submit">
                    <input
                        type="submit"
                        className="submit"
                        value={ __( 'Post Comment', TEXT_DOMAIN ) }
                        disabled
                    />
                </p>
            </form>
        </div>
    );
}
