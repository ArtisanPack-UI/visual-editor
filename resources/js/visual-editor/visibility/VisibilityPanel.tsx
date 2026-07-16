/**
 * Inspector "Visibility" panel that hosts a subsection per rule
 * family. Each subsection is gated by a top-level ToggleControl; when
 * the toggle is on, the subsection's controls render underneath. The
 * toggle's "active" state is derived from **presence of the rule
 * slice on the attribute bag**, not from the depth of its contents —
 * otherwise a rule with an empty list (e.g. `breakpoints: []`) would
 * snap the toggle back off on the next render.
 *
 * The panel writes back a normalized `VisibilityAttribute` on every
 * mutation. Empty rule slices are dropped only when the editor
 * explicitly toggles them off — an active-but-empty rule stays on the
 * bag so the corresponding subsection stays expanded.
 *
 * Layout note: `PanelRow` is a flex container built for one label + one
 * value. Grouped controls (a query-string clause's KEY + VALUE + Remove,
 * a recurring window's DAY + START + END + Remove, etc.) go inside a
 * plain `<div>` with a column layout instead. Only the top-level
 * toggles get a `PanelRow`.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
    Button,
    ButtonGroup,
    BaseControl,
    CheckboxControl,
    PanelBody,
    PanelRow,
    SelectControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';

import type {
    BrowserOsDeviceRuleAttrs,
    DateTimeWindowRuleAttrs,
    QueryStringClause,
    QueryStringRuleAttrs,
    RecurringScheduleRuleAttrs,
    RecurringWindow,
    ReferrerRuleAttrs,
    ScreenSizeRuleAttrs,
    SpecificUserRef,
    SpecificUserRuleAttrs,
    UserRoleRuleAttrs,
    VisibilityAttribute,
} from './types';

interface Props {
    value: VisibilityAttribute | null;
    onChange: (next: VisibilityAttribute | null) => void;
    breakpointOptions: Array<{ key: string; label: string }>;
    roleOptions: Array<{ slug: string; label: string }>;
    searchUsers: (term: string) => Promise<SpecificUserRef[]>;
}

const BROWSER_OPTIONS = [
    { value: 'chrome',  label: 'Chrome' },
    { value: 'firefox', label: 'Firefox' },
    { value: 'safari',  label: 'Safari' },
    { value: 'edge',    label: 'Edge' },
    { value: 'opera',   label: 'Opera' },
    { value: 'ie',      label: 'Internet Explorer' },
    { value: 'other',   label: 'Other' },
];

const OS_OPTIONS = [
    { value: 'windows',  label: 'Windows' },
    { value: 'macos',    label: 'macOS' },
    { value: 'ios',      label: 'iOS' },
    { value: 'android',  label: 'Android' },
    { value: 'linux',    label: 'Linux' },
    { value: 'chromeos', label: 'ChromeOS' },
    { value: 'other',    label: 'Other' },
];

const DEVICE_OPTIONS = [
    { value: 'mobile',  label: 'Mobile' },
    { value: 'tablet',  label: 'Tablet' },
    { value: 'desktop', label: 'Desktop' },
    { value: 'bot',     label: 'Bot' },
];

const DAY_OPTIONS = [
    { value: '0', label: 'Sunday' },
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
];

const SUBSECTION_STYLE: React.CSSProperties = {
    display:       'flex',
    flexDirection: 'column',
    gap:           '8px',
    padding:       '8px 0 16px 24px',
    borderLeft:    '2px solid rgba(0,0,0,0.08)',
    marginLeft:    '8px',
    marginBottom:  '8px',
};

const ROW_STYLE: React.CSSProperties = {
    display:  'flex',
    flexWrap: 'wrap',
    gap:      '8px',
    alignItems: 'flex-end',
};

const REMOVE_BUTTON_STYLE: React.CSSProperties = {
    alignSelf: 'flex-end',
    marginTop: '4px',
};

export function VisibilityPanel({ value, onChange, breakpointOptions, roleOptions, searchUsers }: Props): JSX.Element {
    const v: VisibilityAttribute = value ?? {};

    function patch(next: VisibilityAttribute): void {
        // Drop `undefined` slices explicitly toggled off; keep active-
        // but-empty slices so their subsections stay expanded.
        const cleaned: VisibilityAttribute = {};
        (Object.keys(next) as Array<keyof VisibilityAttribute>).forEach((key) => {
            const slice = next[key];
            if (slice !== undefined) {
                (cleaned as Record<string, unknown>)[key] = slice;
            }
        });

        onChange(Object.keys(cleaned).length === 0 ? null : cleaned);
    }

    return (
        <PanelBody title={__('Visibility', 'artisanpack-visual-editor')} initialOpen={false}>
            <HidePanel value={v.hide} onChange={(next) => patch({ ...v, hide: next })} />
            <ScreenSizePanel
                value={v.screenSize}
                onChange={(next) => patch({ ...v, screenSize: next })}
                breakpoints={breakpointOptions}
            />
            <QueryStringPanel
                value={v.queryString}
                onChange={(next) => patch({ ...v, queryString: next })}
            />
            <ReferrerPanel
                value={v.referrer}
                onChange={(next) => patch({ ...v, referrer: next })}
            />
            <BrowserOsDevicePanel
                value={v.browserOsDevice}
                onChange={(next) => patch({ ...v, browserOsDevice: next })}
            />
            <LoginStatePanel
                value={v.loginState?.state}
                onChange={(state) => patch({ ...v, loginState: state === 'either' ? undefined : { state } })}
            />
            <UserRolePanel
                value={v.userRole}
                onChange={(next) => patch({ ...v, userRole: next })}
                roles={roleOptions}
            />
            <SpecificUserPanel
                value={v.specificUser}
                onChange={(next) => patch({ ...v, specificUser: next })}
                searchUsers={searchUsers}
            />
            <DateTimeWindowPanel
                value={v.dateTimeWindow}
                onChange={(next) => patch({ ...v, dateTimeWindow: next })}
            />
            <RecurringPanel
                value={v.recurring}
                onChange={(next) => patch({ ...v, recurring: next })}
            />
        </PanelBody>
    );
}

function HidePanel({ value, onChange }: { value: VisibilityAttribute['hide']; onChange: (next: VisibilityAttribute['hide']) => void }): JSX.Element {
    return (
        <PanelRow>
            <ToggleControl
                label={__('Hide block', 'artisanpack-visual-editor')}
                help={__('Removes the block from rendered output. The editor canvas still shows a dimmed preview.', 'artisanpack-visual-editor')}
                checked={value?.hidden === true}
                onChange={(hidden) => onChange(hidden ? { hidden: true } : undefined)}
            />
        </PanelRow>
    );
}

function ScreenSizePanel({ value, onChange, breakpoints }: { value: ScreenSizeRuleAttrs | undefined; onChange: (next: ScreenSizeRuleAttrs | undefined) => void; breakpoints: Array<{ key: string; label: string }> }): JSX.Element {
    const active = value !== undefined;

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Screen size', 'artisanpack-visual-editor')}
                    help={__('Hide this block at specific viewport widths.', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'hide', breakpoints: [] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <strong>{__('Hide on:', 'artisanpack-visual-editor')}</strong>
                    {breakpoints.map((bp) => (
                        <CheckboxControl
                            key={bp.key}
                            label={bp.label}
                            checked={(value?.breakpoints ?? []).includes(bp.key)}
                            onChange={(checked) => {
                                const current = new Set(value?.breakpoints ?? []);
                                if (checked) { current.add(bp.key); } else { current.delete(bp.key); }
                                // `direction: 'hide'` is pinned — the UI has no
                                // inverse mode. The attribute is still written
                                // so the persisted shape stays stable with
                                // the rule's schema.
                                onChange({ ...(value ?? {}), direction: 'hide', breakpoints: Array.from(current) });
                            }}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

function QueryStringPanel({ value, onChange }: { value: QueryStringRuleAttrs | undefined; onChange: (next: QueryStringRuleAttrs | undefined) => void }): JSX.Element {
    const active = value !== undefined;
    const clauses = value?.clauses ?? [];

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Query string', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'show', combinator: 'any', clauses: [{ key: '', value: '' }] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, clauses })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, clauses })} />
                    {clauses.map((clause: QueryStringClause, index: number) => (
                        <div key={index} style={{ ...SUBSECTION_STYLE, borderLeft: 'none', padding: '0', margin: '0' }}>
                            <TextControl
                                label={__('Key', 'artisanpack-visual-editor')}
                                value={clause.key}
                                onChange={(next) => {
                                    const copy = [...clauses];
                                    copy[index] = { ...clause, key: next };
                                    onChange({ ...(value ?? {}), clauses: copy });
                                }}
                            />
                            <TextControl
                                label={__('Value ("*" = any)', 'artisanpack-visual-editor')}
                                value={clause.value}
                                onChange={(next) => {
                                    const copy = [...clauses];
                                    copy[index] = { ...clause, value: next };
                                    onChange({ ...(value ?? {}), clauses: copy });
                                }}
                            />
                            <Button
                                variant="link"
                                isDestructive
                                style={REMOVE_BUTTON_STYLE}
                                onClick={() => onChange({ ...(value ?? {}), clauses: clauses.filter((_c, i) => i !== index) })}
                            >
                                {__('Remove clause', 'artisanpack-visual-editor')}
                            </Button>
                        </div>
                    ))}
                    <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), clauses: [...clauses, { key: '', value: '' }] })}>
                        {__('Add clause', 'artisanpack-visual-editor')}
                    </Button>
                </div>
            )}
        </>
    );
}

function ReferrerPanel({ value, onChange }: { value: ReferrerRuleAttrs | undefined; onChange: (next: ReferrerRuleAttrs | undefined) => void }): JSX.Element {
    const active = value !== undefined;
    const patterns = value?.patterns ?? [];

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Referrer', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'show', combinator: 'any', patterns: [''] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, patterns })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, patterns })} />
                    {patterns.map((pattern: string, index: number) => (
                        <div key={index}>
                            <TextControl
                                label={__('Pattern', 'artisanpack-visual-editor')}
                                help={__('e.g. twitter.com, *.example.com, (direct)', 'artisanpack-visual-editor')}
                                value={pattern}
                                onChange={(next) => {
                                    const copy = [...patterns];
                                    copy[index] = next;
                                    onChange({ ...(value ?? {}), patterns: copy });
                                }}
                            />
                            <Button
                                variant="link"
                                isDestructive
                                style={REMOVE_BUTTON_STYLE}
                                onClick={() => onChange({ ...(value ?? {}), patterns: patterns.filter((_p, i) => i !== index) })}
                            >
                                {__('Remove pattern', 'artisanpack-visual-editor')}
                            </Button>
                        </div>
                    ))}
                    <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), patterns: [...patterns, ''] })}>
                        {__('Add pattern', 'artisanpack-visual-editor')}
                    </Button>
                </div>
            )}
        </>
    );
}

function BrowserOsDevicePanel({ value, onChange }: { value: BrowserOsDeviceRuleAttrs | undefined; onChange: (next: BrowserOsDeviceRuleAttrs | undefined) => void }): JSX.Element {
    const active = value !== undefined;

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Browser / OS / Device', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'show', browsers: [], operatingSystems: [], deviceTypes: [] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction })} />
                    <ChipList label={__('Browsers', 'artisanpack-visual-editor')} options={BROWSER_OPTIONS} value={value?.browsers ?? []} onChange={(next) => onChange({ ...(value ?? {}), browsers: next })} />
                    <ChipList label={__('Operating systems', 'artisanpack-visual-editor')} options={OS_OPTIONS} value={value?.operatingSystems ?? []} onChange={(next) => onChange({ ...(value ?? {}), operatingSystems: next })} />
                    <ChipList label={__('Device types', 'artisanpack-visual-editor')} options={DEVICE_OPTIONS} value={value?.deviceTypes ?? []} onChange={(next) => onChange({ ...(value ?? {}), deviceTypes: next })} />
                </div>
            )}
        </>
    );
}

function LoginStatePanel({ value, onChange }: { value: 'loggedIn' | 'loggedOut' | 'either' | undefined; onChange: (next: 'loggedIn' | 'loggedOut' | 'either') => void }): JSX.Element {
    return (
        <PanelRow>
            <SelectControl
                label={__('Login state', 'artisanpack-visual-editor')}
                value={value ?? 'either'}
                options={[
                    { value: 'either',    label: __('Either', 'artisanpack-visual-editor') },
                    { value: 'loggedIn',  label: __('Logged in', 'artisanpack-visual-editor') },
                    { value: 'loggedOut', label: __('Logged out', 'artisanpack-visual-editor') },
                ]}
                onChange={(next) => onChange(next as 'loggedIn' | 'loggedOut' | 'either')}
            />
        </PanelRow>
    );
}

function UserRolePanel({ value, onChange, roles }: { value: UserRoleRuleAttrs | undefined; onChange: (next: UserRoleRuleAttrs | undefined) => void; roles: Array<{ slug: string; label: string }> }): JSX.Element {
    const active = value !== undefined;

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('User role', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'show', combinator: 'any', roles: [] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, roles: value?.roles ?? [] })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, roles: value?.roles ?? [] })} />
                    {roles.length === 0 && (
                        <em style={{ opacity: 0.7 }}>
                            {__('No roles are registered. Add roles to your app to use this rule.', 'artisanpack-visual-editor')}
                        </em>
                    )}
                    <ChipList
                        label={__('Roles', 'artisanpack-visual-editor')}
                        options={roles.map((r) => ({ value: r.slug, label: r.label }))}
                        value={value?.roles ?? []}
                        onChange={(next) => onChange({ ...(value ?? {}), roles: next })}
                    />
                </div>
            )}
        </>
    );
}

function SpecificUserPanel({ value, onChange, searchUsers }: { value: SpecificUserRuleAttrs | undefined; onChange: (next: SpecificUserRuleAttrs | undefined) => void; searchUsers: (term: string) => Promise<SpecificUserRef[]> }): JSX.Element {
    const active = value !== undefined;
    const users = value?.users ?? [];

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Specific user', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'show', users: [] } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, users })} />
                    <UserAutocomplete
                        searchUsers={searchUsers}
                        onSelect={(user) => {
                            if (users.some((u) => u.id === user.id)) { return; }
                            onChange({ ...(value ?? {}), users: [...users, user] });
                        }}
                    />
                    {users.length > 0 && (
                        <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '4px' }}>
                            {users.map((user: SpecificUserRef) => (
                                <li key={user.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '8px' }}>
                                    <span>{user.name ? `${user.name} (${user.email})` : user.email}</span>
                                    <Button
                                        variant="link"
                                        isDestructive
                                        onClick={() => onChange({ ...(value ?? {}), users: users.filter((u) => u.id !== user.id) })}
                                    >
                                        {__('Remove', 'artisanpack-visual-editor')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </>
    );
}

function DateTimeWindowPanel({ value, onChange }: { value: DateTimeWindowRuleAttrs | undefined; onChange: (next: DateTimeWindowRuleAttrs | undefined) => void }): JSX.Element {
    const active = value !== undefined;

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Date / time window', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { start: '', end: '', timezone: '' } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    <DateTimeInput
                        label={__('Start', 'artisanpack-visual-editor')}
                        value={toLocalIsoFormat(value?.start)}
                        onChange={(next) => onChange({ ...(value ?? {}), start: next })}
                    />
                    <DateTimeInput
                        label={__('End', 'artisanpack-visual-editor')}
                        value={toLocalIsoFormat(value?.end)}
                        onChange={(next) => onChange({ ...(value ?? {}), end: next })}
                    />
                    <TextControl
                        label={__('Timezone override (optional)', 'artisanpack-visual-editor')}
                        help={__('e.g. America/Chicago. Falls back to the app\'s configured timezone.', 'artisanpack-visual-editor')}
                        value={value?.timezone ?? ''}
                        onChange={(next) => onChange({ ...(value ?? {}), timezone: next })}
                    />
                </div>
            )}
        </>
    );
}

function RecurringPanel({ value, onChange }: { value: RecurringScheduleRuleAttrs | undefined; onChange: (next: RecurringScheduleRuleAttrs | undefined) => void }): JSX.Element {
    const active = value !== undefined;
    const windows = value?.windows ?? [];

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Recurring schedule', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { windows: [{ day: 1, start: '09:00', end: '17:00' }], timezone: '' } : undefined)}
                />
            </PanelRow>
            {active && (
                <div style={SUBSECTION_STYLE}>
                    {windows.map((win: RecurringWindow, index: number) => (
                        <div key={index} style={{ display: 'flex', flexDirection: 'column', gap: '4px', paddingBottom: '8px', borderBottom: '1px dashed rgba(0,0,0,0.08)' }}>
                            <div style={ROW_STYLE}>
                                <div style={{ flex: '1 1 100px', minWidth: '100px' }}>
                                    <SelectControl
                                        label={__('Day', 'artisanpack-visual-editor')}
                                        value={String(win.day)}
                                        options={DAY_OPTIONS}
                                        onChange={(next) => {
                                            const copy = [...windows];
                                            copy[index] = { ...win, day: Number(next) };
                                            onChange({ ...(value ?? {}), windows: copy });
                                        }}
                                    />
                                </div>
                                <div style={{ flex: '1 1 80px', minWidth: '80px' }}>
                                    <TextControl
                                        label={__('Start', 'artisanpack-visual-editor')}
                                        placeholder="HH:MM"
                                        value={win.start}
                                        onChange={(next) => {
                                            const copy = [...windows];
                                            copy[index] = { ...win, start: next };
                                            onChange({ ...(value ?? {}), windows: copy });
                                        }}
                                    />
                                </div>
                                <div style={{ flex: '1 1 80px', minWidth: '80px' }}>
                                    <TextControl
                                        label={__('End', 'artisanpack-visual-editor')}
                                        placeholder="HH:MM"
                                        value={win.end}
                                        onChange={(next) => {
                                            const copy = [...windows];
                                            copy[index] = { ...win, end: next };
                                            onChange({ ...(value ?? {}), windows: copy });
                                        }}
                                    />
                                </div>
                            </div>
                            <Button
                                variant="link"
                                isDestructive
                                style={REMOVE_BUTTON_STYLE}
                                onClick={() => onChange({ ...(value ?? {}), windows: windows.filter((_w, i) => i !== index) })}
                            >
                                {__('Remove window', 'artisanpack-visual-editor')}
                            </Button>
                        </div>
                    ))}
                    <Button
                        variant="secondary"
                        disabled={windows.length >= 14}
                        onClick={() => onChange({ ...(value ?? {}), windows: [...windows, { day: 1, start: '09:00', end: '17:00' }] })}
                    >
                        {__('Add window', 'artisanpack-visual-editor')}
                    </Button>
                    <TextControl
                        label={__('Timezone override (optional)', 'artisanpack-visual-editor')}
                        value={value?.timezone ?? ''}
                        onChange={(next) => onChange({ ...(value ?? {}), timezone: next, windows })}
                    />
                </div>
            )}
        </>
    );
}

function DirectionToggle({ value, onChange }: { value: 'show' | 'hide'; onChange: (next: 'show' | 'hide') => void }): JSX.Element {
    return (
        <ButtonGroup>
            <Button variant={value === 'show' ? 'primary' : 'secondary'} onClick={() => onChange('show')}>{__('Show when', 'artisanpack-visual-editor')}</Button>
            <Button variant={value === 'hide' ? 'primary' : 'secondary'} onClick={() => onChange('hide')}>{__('Hide when', 'artisanpack-visual-editor')}</Button>
        </ButtonGroup>
    );
}

function CombinatorToggle({ value, onChange }: { value: 'any' | 'all'; onChange: (next: 'any' | 'all') => void }): JSX.Element {
    return (
        <ButtonGroup>
            <Button variant={value === 'any' ? 'primary' : 'secondary'} onClick={() => onChange('any')}>{__('Any', 'artisanpack-visual-editor')}</Button>
            <Button variant={value === 'all' ? 'primary' : 'secondary'} onClick={() => onChange('all')}>{__('All', 'artisanpack-visual-editor')}</Button>
        </ButtonGroup>
    );
}

function ChipList({ label, options, value, onChange }: { label: string; options: Array<{ value: string; label: string }>; value: string[]; onChange: (next: string[]) => void }): JSX.Element {
    return (
        <fieldset style={{ border: 'none', padding: 0, margin: 0 }}>
            <legend style={{ padding: 0, marginBottom: '4px', fontWeight: 600 }}>{label}</legend>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                {options.map((opt) => (
                    <CheckboxControl
                        key={opt.value}
                        label={opt.label}
                        checked={value.includes(opt.value)}
                        onChange={(checked) => {
                            const set = new Set(value);
                            if (checked) { set.add(opt.value); } else { set.delete(opt.value); }
                            onChange(Array.from(set));
                        }}
                    />
                ))}
            </div>
        </fieldset>
    );
}

function DateTimeInput({ label, value, onChange }: { label: string; value: string; onChange: (next: string) => void }): JSX.Element {
    // Native `<input type="datetime-local">` produces / accepts
    // "YYYY-MM-DDTHH:MM" — the same format Carbon parses on the PHP
    // side. Preferred over `@wordpress/components`'s DateTimePicker
    // because that component draws a full-panel calendar (~450px tall)
    // which is too heavy for an inspector row.
    return (
        <BaseControl label={label} __nextHasNoMarginBottom={true} id={`ve-vis-datetime-${label}`}>
            <input
                type="datetime-local"
                id={`ve-vis-datetime-${label}`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                style={{ width: '100%', padding: '6px 8px', boxSizing: 'border-box' }}
            />
        </BaseControl>
    );
}

function toLocalIsoFormat(value: string | undefined): string {
    // Passthrough for values already in the `YYYY-MM-DDTHH:MM[:SS]`
    // shape (what the native input expects). Falsy → empty. Trailing
    // seconds are stripped because `datetime-local` rejects strings
    // with a "Z" suffix or a numeric timezone offset.
    if (!value) { return ''; }

    // Strip any trailing timezone marker; the PHP side interprets the
    // string in the rule's `timezone` field, not in the input.
    const stripped = value.replace(/(Z|[+-]\d{2}:?\d{2})$/, '');

    // Trim to "YYYY-MM-DDTHH:MM" (16 chars) — datetime-local ignores
    // trailing seconds inconsistently across browsers.
    return stripped.length >= 16 ? stripped.slice(0, 16) : stripped;
}

function UserAutocomplete({ searchUsers, onSelect }: { searchUsers: (term: string) => Promise<SpecificUserRef[]>; onSelect: (user: SpecificUserRef) => void }): JSX.Element {
    const [term, setTerm]       = useState('');
    const [results, setResults] = useState<SpecificUserRef[]>([]);

    useEffect(() => {
        if (term.trim().length < 2) { setResults([]); return; }
        let cancelled = false;
        searchUsers(term).then((r) => { if (!cancelled) { setResults(r); } }).catch(() => { if (!cancelled) { setResults([]); } });
        return () => { cancelled = true; };
    }, [term, searchUsers]);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
            <TextControl
                label={__('Search users', 'artisanpack-visual-editor')}
                value={term}
                onChange={setTerm}
            />
            {results.length > 0 && (
                <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: '2px', maxHeight: '200px', overflow: 'auto' }}>
                    {results.map((user) => (
                        <li key={user.id}>
                            <Button variant="tertiary" onClick={() => { onSelect(user); setTerm(''); }}>
                                {user.name ? `${user.name} (${user.email})` : user.email}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
