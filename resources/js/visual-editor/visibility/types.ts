/**
 * Client-side type definitions for the Block Visibility attribute bag.
 *
 * The persisted shape mirrors the PHP-side rule slice keys used by
 * `\ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator`. Any
 * change here needs a matching update on the rule class handling the
 * corresponding slice.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.4.0
 */

export type Direction = 'show' | 'hide';
export type Combinator = 'any' | 'all';

export interface HideRuleAttrs {
    hidden?: boolean;
}

export interface ScreenSizeRuleAttrs {
    direction?: Direction;
    breakpoints?: string[];
}

export interface QueryStringClause {
    key: string;
    value: string;
}

export interface QueryStringRuleAttrs {
    direction?: Direction;
    combinator?: Combinator;
    clauses?: QueryStringClause[];
}

export interface ReferrerRuleAttrs {
    direction?: Direction;
    combinator?: Combinator;
    patterns?: string[];
}

export interface BrowserOsDeviceRuleAttrs {
    direction?: Direction;
    browsers?: string[];
    operatingSystems?: string[];
    deviceTypes?: string[];
}

export type LoginState = 'loggedIn' | 'loggedOut' | 'either';

export interface LoginStateRuleAttrs {
    state?: LoginState;
}

export interface UserRoleRuleAttrs {
    direction?: Direction;
    combinator?: Combinator;
    roles?: string[];
}

export interface SpecificUserRef {
    // Numeric primary keys OR UUID / other string keys (`HasUuids`
    // hosts). Both round-trip verbatim through the picker + PHP rule.
    id: number | string;
    email: string;
    name?: string;
}

export interface SpecificUserRuleAttrs {
    direction?: Direction;
    users?: SpecificUserRef[];
}

export interface DateTimeWindowRuleAttrs {
    start?: string;
    end?: string;
    timezone?: string;
}

export interface RecurringWindow {
    day: number;
    start: string;
    end: string;
}

export interface RecurringScheduleRuleAttrs {
    timezone?: string;
    windows?: RecurringWindow[];
}

export interface VisibilityAttribute {
    hide?: HideRuleAttrs;
    screenSize?: ScreenSizeRuleAttrs;
    queryString?: QueryStringRuleAttrs;
    referrer?: ReferrerRuleAttrs;
    browserOsDevice?: BrowserOsDeviceRuleAttrs;
    loginState?: LoginStateRuleAttrs;
    userRole?: UserRoleRuleAttrs;
    specificUser?: SpecificUserRuleAttrs;
    dateTimeWindow?: DateTimeWindowRuleAttrs;
    recurring?: RecurringScheduleRuleAttrs;
}

export const RULE_KEYS = [
    'hide',
    'screenSize',
    'queryString',
    'referrer',
    'browserOsDevice',
    'loginState',
    'userRole',
    'specificUser',
    'dateTimeWindow',
    'recurring',
] as const;

export type RuleKey = (typeof RULE_KEYS)[number];

/**
 * Returns `true` when any rule slice on the attribute bag has content
 * (used by the toolbar Eye badge + panel's "any rule active" logic).
 */
export function isVisibilityActive(value: VisibilityAttribute | null | undefined): boolean {
    if (!value) {
        return false;
    }

    if (value.hide?.hidden === true) {
        return true;
    }

    if ((value.screenSize?.breakpoints?.length ?? 0) > 0) {
        return true;
    }

    if ((value.queryString?.clauses?.length ?? 0) > 0) {
        return true;
    }

    if ((value.referrer?.patterns?.length ?? 0) > 0) {
        return true;
    }

    if (
        (value.browserOsDevice?.browsers?.length ?? 0) > 0 ||
        (value.browserOsDevice?.operatingSystems?.length ?? 0) > 0 ||
        (value.browserOsDevice?.deviceTypes?.length ?? 0) > 0
    ) {
        return true;
    }

    if (value.loginState?.state && value.loginState.state !== 'either') {
        return true;
    }

    if ((value.userRole?.roles?.length ?? 0) > 0) {
        return true;
    }

    if ((value.specificUser?.users?.length ?? 0) > 0) {
        return true;
    }

    if (value.dateTimeWindow?.start || value.dateTimeWindow?.end) {
        return true;
    }

    if ((value.recurring?.windows?.length ?? 0) > 0) {
        return true;
    }

    return false;
}
