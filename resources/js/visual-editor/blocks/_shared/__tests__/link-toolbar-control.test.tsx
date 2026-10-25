/**
 * Tests for the shared toolbar link control (#1022).
 */

import { describe, it, expect, vi } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/icons', () => ({
    link: 'link-icon',
    linkOff: 'link-off-icon',
}));

vi.mock('@wordpress/url', () => ({
    prependHTTPS: (url: string) =>
        url === '' || /^([a-z]+:|[/#?])/i.test(url) ? url : `https://${url}`,
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="block-controls">{children}</div>
    ),
}));

vi.mock('@wordpress/components', () => ({
    Popover: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="popover">{children}</div>
    ),
    ToolbarButton: ({ title, onClick }: { title: string; onClick: () => void }) => (
        <button onClick={onClick}>{title}</button>
    ),
}));

const linkControlProps: Record<string, unknown>[] = [];

vi.mock('../../../dynamic-content/link-control', () => ({
    ArtisanPackLinkControl: (props: Record<string, unknown>) => {
        linkControlProps.push(props);
        const onChange = props.onChange as (next: Record<string, unknown>) => void;
        return (
            <div data-testid="link-control">
                <button
                    data-testid="apply"
                    onClick={() =>
                        onChange({ url: 'example.com', opensInNewTab: true, nofollow: true })
                    }
                />
                <button data-testid="remove" onClick={props.onRemove as () => void} />
            </div>
        );
    },
}));

(globalThis as { React?: unknown }).React = require('react');

import LinkToolbarControl, { hasNofollow, resolveLinkChange } from '../link-toolbar-control';

describe('resolveLinkChange', () => {
    it('adds the new-tab and nofollow rel tokens and prepends https', () => {
        expect(
            resolveLinkChange({ url: 'example.com', opensInNewTab: true, nofollow: true }, '')
        ).toEqual({
            url: 'https://example.com',
            linkTarget: '_blank',
            rel: 'noreferrer noopener nofollow',
        });
    });

    it('removes the new-tab tokens whatever order they were stored in', () => {
        expect(
            resolveLinkChange(
                { url: 'https://example.com', opensInNewTab: false, nofollow: true },
                'noopener nofollow noreferrer'
            )
        ).toEqual({ url: 'https://example.com', linkTarget: undefined, rel: 'nofollow' });
    });

    it.each(['{{site.facebook}}', 'mailto:{{site.email}}', '/about', '#top'])(
        'leaves %s without an https:// prefix',
        (url) => {
            expect(resolveLinkChange({ url }, undefined).url).toBe(url);
        }
    );

    it('strips the new-tab and nofollow tokens but keeps author tokens', () => {
        expect(
            resolveLinkChange(
                { url: 'https://example.com', opensInNewTab: false, nofollow: false },
                'sponsored noreferrer noopener nofollow'
            )
        ).toEqual({ url: 'https://example.com', linkTarget: undefined, rel: 'sponsored' });
    });

    it('returns an undefined rel when no tokens remain', () => {
        expect(resolveLinkChange({ url: '/about' }, undefined).rel).toBeUndefined();
    });
});

describe('hasNofollow', () => {
    it.each([
        [undefined, false],
        ['', false],
        ['noopener', false],
        ['nofollowing', false],
        ['noopener nofollow', true],
    ])('%s → %s', (rel, expected) => {
        expect(hasNofollow(rel)).toBe(expected);
    });
});

describe('LinkToolbarControl', () => {
    it('shows only the Link button when there is no URL', () => {
        const { getByText, queryByText, queryByTestId } = render(
            <LinkToolbarControl onChange={vi.fn()} onRemove={vi.fn()} />
        );
        expect(getByText('Link')).toBeTruthy();
        expect(queryByText('Unlink')).toBeNull();
        expect(queryByTestId('popover')).toBeNull();
    });

    it('opens the popover with the current value and reports normalized changes', () => {
        linkControlProps.length = 0;
        const onChange = vi.fn();
        const { getByText, getByTestId } = render(
            <LinkToolbarControl
                url="https://example.com"
                linkTarget="_blank"
                rel="noopener nofollow"
                onChange={onChange}
                onRemove={vi.fn()}
            />
        );
        fireEvent.click(getByText('Link'));

        expect(linkControlProps[0].value).toEqual({
            url: 'https://example.com',
            opensInNewTab: true,
            nofollow: true,
        });
        expect((linkControlProps[0].settings as { id: string }[]).map((s) => s.id)).toEqual([
            'opensInNewTab',
            'nofollow',
        ]);

        fireEvent.click(getByTestId('apply'));
        expect(onChange).toHaveBeenCalledWith({
            url: 'https://example.com',
            linkTarget: '_blank',
            rel: 'noreferrer noopener nofollow',
        });
    });

    it('unlinks from the toolbar and from the popover', () => {
        const onRemove = vi.fn();
        const { getByText, getByTestId, queryByTestId } = render(
            <LinkToolbarControl url="https://example.com" onChange={vi.fn()} onRemove={onRemove} />
        );
        fireEvent.click(getByText('Unlink'));
        expect(onRemove).toHaveBeenCalledTimes(1);

        fireEvent.click(getByText('Link'));
        fireEvent.click(getByTestId('remove'));
        expect(onRemove).toHaveBeenCalledTimes(2);
        expect(queryByTestId('popover')).toBeNull();
    });
});
