/**
 * Inspector "Visibility" panel that hosts a subsection per rule
 * family. Subsections only render once the editor has *added* the
 * corresponding rule (via a per-family "Add rule" button) so an
 * unused panel stays uncluttered — no expanded-but-empty rows.
 *
 * The panel writes back a normalized `VisibilityAttribute` on every
 * mutation. Empty rule slices are dropped so the persisted attribute
 * is the minimal shape possible (no trailing `screenSize: {}` when
 * the editor removed all breakpoints, for instance).
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
    Button,
    ButtonGroup,
    CheckboxControl,
    DateTimePicker,
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

export function VisibilityPanel({ value, onChange, breakpointOptions, roleOptions, searchUsers }: Props): JSX.Element {
    const v: VisibilityAttribute = value ?? {};

    function patch(next: VisibilityAttribute): void {
        // Drop empty slices so persisted attribute stays minimal.
        const cleaned: VisibilityAttribute = {};
        (Object.keys(next) as Array<keyof VisibilityAttribute>).forEach((key) => {
            const slice = next[key];
            if (slice && Object.keys(slice as object).length > 0) {
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
    const active = (value?.breakpoints ?? []).length > 0;

    return (
        <>
            <PanelRow>
                <ToggleControl
                    label={__('Screen size', 'artisanpack-visual-editor')}
                    checked={active}
                    onChange={(next) => onChange(next ? { direction: 'hide', breakpoints: [] } : undefined)}
                />
            </PanelRow>
            {active && (
                <>
                    <DirectionToggle
                        value={value?.direction ?? 'hide'}
                        onChange={(direction) => onChange({ ...(value ?? {}), direction, breakpoints: value?.breakpoints ?? [] })}
                    />
                    {breakpoints.map((bp) => (
                        <PanelRow key={bp.key}>
                            <CheckboxControl
                                label={bp.label}
                                checked={(value?.breakpoints ?? []).includes(bp.key)}
                                onChange={(checked) => {
                                    const current = new Set(value?.breakpoints ?? []);
                                    if (checked) { current.add(bp.key); } else { current.delete(bp.key); }
                                    onChange({ ...(value ?? {}), direction: value?.direction ?? 'hide', breakpoints: Array.from(current) });
                                }}
                            />
                        </PanelRow>
                    ))}
                </>
            )}
        </>
    );
}

function QueryStringPanel({ value, onChange }: { value: QueryStringRuleAttrs | undefined; onChange: (next: QueryStringRuleAttrs | undefined) => void }): JSX.Element {
    const clauses = value?.clauses ?? [];
    const active = clauses.length > 0;

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
                <>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, clauses })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, clauses })} />
                    {clauses.map((clause: QueryStringClause, index: number) => (
                        <PanelRow key={index}>
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
                                variant="secondary"
                                onClick={() => onChange({ ...(value ?? {}), clauses: clauses.filter((_c, i) => i !== index) })}
                            >
                                {__('Remove', 'artisanpack-visual-editor')}
                            </Button>
                        </PanelRow>
                    ))}
                    <PanelRow>
                        <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), clauses: [...clauses, { key: '', value: '' }] })}>
                            {__('Add clause', 'artisanpack-visual-editor')}
                        </Button>
                    </PanelRow>
                </>
            )}
        </>
    );
}

function ReferrerPanel({ value, onChange }: { value: ReferrerRuleAttrs | undefined; onChange: (next: ReferrerRuleAttrs | undefined) => void }): JSX.Element {
    const patterns = value?.patterns ?? [];
    const active = patterns.length > 0;

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
                <>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, patterns })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, patterns })} />
                    {patterns.map((pattern: string, index: number) => (
                        <PanelRow key={index}>
                            <TextControl
                                label={__('Pattern (e.g. twitter.com, *.example.com, (direct))', 'artisanpack-visual-editor')}
                                value={pattern}
                                onChange={(next) => {
                                    const copy = [...patterns];
                                    copy[index] = next;
                                    onChange({ ...(value ?? {}), patterns: copy });
                                }}
                            />
                            <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), patterns: patterns.filter((_p, i) => i !== index) })}>
                                {__('Remove', 'artisanpack-visual-editor')}
                            </Button>
                        </PanelRow>
                    ))}
                    <PanelRow>
                        <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), patterns: [...patterns, ''] })}>
                            {__('Add pattern', 'artisanpack-visual-editor')}
                        </Button>
                    </PanelRow>
                </>
            )}
        </>
    );
}

function BrowserOsDevicePanel({ value, onChange }: { value: BrowserOsDeviceRuleAttrs | undefined; onChange: (next: BrowserOsDeviceRuleAttrs | undefined) => void }): JSX.Element {
    const active = (value?.browsers?.length ?? 0) + (value?.operatingSystems?.length ?? 0) + (value?.deviceTypes?.length ?? 0) > 0;

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
                <>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction })} />
                    <ChipList label={__('Browsers', 'artisanpack-visual-editor')} options={BROWSER_OPTIONS} value={value?.browsers ?? []} onChange={(next) => onChange({ ...(value ?? {}), browsers: next })} />
                    <ChipList label={__('Operating systems', 'artisanpack-visual-editor')} options={OS_OPTIONS} value={value?.operatingSystems ?? []} onChange={(next) => onChange({ ...(value ?? {}), operatingSystems: next })} />
                    <ChipList label={__('Device types', 'artisanpack-visual-editor')} options={DEVICE_OPTIONS} value={value?.deviceTypes ?? []} onChange={(next) => onChange({ ...(value ?? {}), deviceTypes: next })} />
                </>
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
    const active = (value?.roles?.length ?? 0) > 0;

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
                <>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, roles: value?.roles ?? [] })} />
                    <CombinatorToggle value={value?.combinator ?? 'any'} onChange={(combinator) => onChange({ ...(value ?? {}), combinator, roles: value?.roles ?? [] })} />
                    <ChipList
                        label={__('Roles', 'artisanpack-visual-editor')}
                        options={roles.map((r) => ({ value: r.slug, label: r.label }))}
                        value={value?.roles ?? []}
                        onChange={(next) => onChange({ ...(value ?? {}), roles: next })}
                    />
                </>
            )}
        </>
    );
}

function SpecificUserPanel({ value, onChange, searchUsers }: { value: SpecificUserRuleAttrs | undefined; onChange: (next: SpecificUserRuleAttrs | undefined) => void; searchUsers: (term: string) => Promise<SpecificUserRef[]> }): JSX.Element {
    const users = value?.users ?? [];
    const active = users.length > 0;

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
                <>
                    <DirectionToggle value={value?.direction ?? 'show'} onChange={(direction) => onChange({ ...(value ?? {}), direction, users })} />
                    <UserAutocomplete
                        searchUsers={searchUsers}
                        onSelect={(user) => {
                            if (users.some((u) => u.id === user.id)) { return; }
                            onChange({ ...(value ?? {}), users: [...users, user] });
                        }}
                    />
                    {users.map((user: SpecificUserRef) => (
                        <PanelRow key={user.id}>
                            <span>{user.name ? `${user.name} (${user.email})` : user.email}</span>
                            <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), users: users.filter((u) => u.id !== user.id) })}>
                                {__('Remove', 'artisanpack-visual-editor')}
                            </Button>
                        </PanelRow>
                    ))}
                </>
            )}
        </>
    );
}

function DateTimeWindowPanel({ value, onChange }: { value: DateTimeWindowRuleAttrs | undefined; onChange: (next: DateTimeWindowRuleAttrs | undefined) => void }): JSX.Element {
    const active = Boolean(value?.start || value?.end);

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
                <>
                    <PanelRow>
                        <DateTimePicker
                            currentDate={value?.start ?? undefined}
                            onChange={(next) => onChange({ ...(value ?? {}), start: next ?? '' })}
                        />
                    </PanelRow>
                    <PanelRow>
                        <DateTimePicker
                            currentDate={value?.end ?? undefined}
                            onChange={(next) => onChange({ ...(value ?? {}), end: next ?? '' })}
                        />
                    </PanelRow>
                    <PanelRow>
                        <TextControl
                            label={__('Timezone override (optional)', 'artisanpack-visual-editor')}
                            help={__('e.g. America/Chicago. Falls back to the app\'s configured timezone.', 'artisanpack-visual-editor')}
                            value={value?.timezone ?? ''}
                            onChange={(next) => onChange({ ...(value ?? {}), timezone: next })}
                        />
                    </PanelRow>
                </>
            )}
        </>
    );
}

function RecurringPanel({ value, onChange }: { value: RecurringScheduleRuleAttrs | undefined; onChange: (next: RecurringScheduleRuleAttrs | undefined) => void }): JSX.Element {
    const windows = value?.windows ?? [];
    const active = windows.length > 0;

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
                <>
                    {windows.map((win: RecurringWindow, index: number) => (
                        <PanelRow key={index}>
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
                            <TextControl
                                label={__('Start (HH:MM)', 'artisanpack-visual-editor')}
                                value={win.start}
                                onChange={(next) => {
                                    const copy = [...windows];
                                    copy[index] = { ...win, start: next };
                                    onChange({ ...(value ?? {}), windows: copy });
                                }}
                            />
                            <TextControl
                                label={__('End (HH:MM)', 'artisanpack-visual-editor')}
                                value={win.end}
                                onChange={(next) => {
                                    const copy = [...windows];
                                    copy[index] = { ...win, end: next };
                                    onChange({ ...(value ?? {}), windows: copy });
                                }}
                            />
                            <Button variant="secondary" onClick={() => onChange({ ...(value ?? {}), windows: windows.filter((_w, i) => i !== index) })}>
                                {__('Remove', 'artisanpack-visual-editor')}
                            </Button>
                        </PanelRow>
                    ))}
                    <PanelRow>
                        <Button
                            variant="secondary"
                            disabled={windows.length >= 14}
                            onClick={() => onChange({ ...(value ?? {}), windows: [...windows, { day: 1, start: '09:00', end: '17:00' }] })}
                        >
                            {__('Add window', 'artisanpack-visual-editor')}
                        </Button>
                    </PanelRow>
                    <PanelRow>
                        <TextControl
                            label={__('Timezone override (optional)', 'artisanpack-visual-editor')}
                            value={value?.timezone ?? ''}
                            onChange={(next) => onChange({ ...(value ?? {}), timezone: next, windows })}
                        />
                    </PanelRow>
                </>
            )}
        </>
    );
}

function DirectionToggle({ value, onChange }: { value: 'show' | 'hide'; onChange: (next: 'show' | 'hide') => void }): JSX.Element {
    return (
        <PanelRow>
            <ButtonGroup>
                <Button variant={value === 'show' ? 'primary' : 'secondary'} onClick={() => onChange('show')}>{__('Show when', 'artisanpack-visual-editor')}</Button>
                <Button variant={value === 'hide' ? 'primary' : 'secondary'} onClick={() => onChange('hide')}>{__('Hide when', 'artisanpack-visual-editor')}</Button>
            </ButtonGroup>
        </PanelRow>
    );
}

function CombinatorToggle({ value, onChange }: { value: 'any' | 'all'; onChange: (next: 'any' | 'all') => void }): JSX.Element {
    return (
        <PanelRow>
            <ButtonGroup>
                <Button variant={value === 'any' ? 'primary' : 'secondary'} onClick={() => onChange('any')}>{__('Any', 'artisanpack-visual-editor')}</Button>
                <Button variant={value === 'all' ? 'primary' : 'secondary'} onClick={() => onChange('all')}>{__('All', 'artisanpack-visual-editor')}</Button>
            </ButtonGroup>
        </PanelRow>
    );
}

function ChipList({ label, options, value, onChange }: { label: string; options: Array<{ value: string; label: string }>; value: string[]; onChange: (next: string[]) => void }): JSX.Element {
    return (
        <PanelRow>
            <fieldset>
                <legend>{label}</legend>
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
            </fieldset>
        </PanelRow>
    );
}

function UserAutocomplete({ searchUsers, onSelect }: { searchUsers: (term: string) => Promise<SpecificUserRef[]>; onSelect: (user: SpecificUserRef) => void }): JSX.Element {
    return (
        <PanelRow>
            <UserAutocompleteInner searchUsers={searchUsers} onSelect={onSelect} />
        </PanelRow>
    );
}

function UserAutocompleteInner({ searchUsers, onSelect }: { searchUsers: (term: string) => Promise<SpecificUserRef[]>; onSelect: (user: SpecificUserRef) => void }): JSX.Element {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<SpecificUserRef[]>([]);

    useEffect(() => {
        if (term.trim().length < 2) { setResults([]); return; }
        let cancelled = false;
        searchUsers(term).then((r) => { if (!cancelled) { setResults(r); } }).catch(() => { if (!cancelled) { setResults([]); } });
        return () => { cancelled = true; };
    }, [term, searchUsers]);

    return (
        <>
            <TextControl
                label={__('Search users', 'artisanpack-visual-editor')}
                value={term}
                onChange={setTerm}
            />
            {results.map((user) => (
                <Button key={user.id} variant="secondary" onClick={() => { onSelect(user); setTerm(''); }}>
                    {user.name ? `${user.name} (${user.email})` : user.email}
                </Button>
            ))}
        </>
    );
}

