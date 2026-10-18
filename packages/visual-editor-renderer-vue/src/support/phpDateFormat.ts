/**
 * PHP `date()` formatting for an ISO 8601 timestamp.
 *
 * `post-date` stores its `format` as a PHP date format string, which the
 * Blade renderer applies through Carbon's `translatedFormat()`. Twins that
 * stamp dates client-side go through this helper so the three renderers
 * agree. The timestamp's own wall-clock time is formatted (its offset is
 * not converted to the viewer's timezone), matching the server output.
 *
 * Supports the day, month, year and time characters (`d D j l N S w z
 * F m M n t L Y y a A g G h H i s`) and backslash escapes; any other
 * character is copied as is. Month and day names come from `Intl` in
 * `locale`.
 *
 * @since 1.15.2
 */

interface DateParts {
    readonly year: number;
    readonly month: number;
    readonly day: number;
    readonly hour: number;
    readonly minute: number;
    readonly second: number;
}

const ISO_PATTERN = /^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/;

/**
 * Format `iso` with the PHP date format `format`. Returns `''` for an
 * empty timestamp and the raw string when it can't be parsed.
 */
export function phpDateFormat(iso: string | null | undefined, format: string, locale = 'en-US'): string {
    if (iso === null || iso === undefined || iso === '') {
        return '';
    }

    const parts = parseIso(iso);

    if (parts === null) {
        return iso;
    }

    const utc = new Date(Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second));
    const name = (options: Intl.DateTimeFormatOptions): string =>
        new Intl.DateTimeFormat(locale, { ...options, timeZone: 'UTC' }).format(utc);
    const weekday = utc.getUTCDay();
    const hour12 = parts.hour % 12 === 0 ? 12 : parts.hour % 12;
    let output = '';

    for (let index = 0; index < format.length; index++) {
        const character = format[index];

        if (character === '\\') {
            index++;
            output += format[index] ?? '';
            continue;
        }

        switch (character) {
            case 'd':
                output += pad(parts.day);
                break;
            case 'D':
                output += name({ weekday: 'short' });
                break;
            case 'j':
                output += String(parts.day);
                break;
            case 'l':
                output += name({ weekday: 'long' });
                break;
            case 'N':
                output += String(weekday === 0 ? 7 : weekday);
                break;
            case 'S':
                output += ordinalSuffix(parts.day);
                break;
            case 'w':
                output += String(weekday);
                break;
            case 'z':
                output += String((Date.UTC(parts.year, parts.month - 1, parts.day) - Date.UTC(parts.year, 0, 1)) / 86400000);
                break;
            case 'F':
                output += name({ month: 'long' });
                break;
            case 'm':
                output += pad(parts.month);
                break;
            case 'M':
                output += name({ month: 'short' });
                break;
            case 'n':
                output += String(parts.month);
                break;
            case 't':
                output += String(new Date(Date.UTC(parts.year, parts.month, 0)).getUTCDate());
                break;
            case 'L':
                output += isLeapYear(parts.year) ? '1' : '0';
                break;
            case 'Y':
                output += String(parts.year);
                break;
            case 'y':
                output += pad(parts.year % 100);
                break;
            case 'a':
                output += parts.hour < 12 ? 'am' : 'pm';
                break;
            case 'A':
                output += parts.hour < 12 ? 'AM' : 'PM';
                break;
            case 'g':
                output += String(hour12);
                break;
            case 'G':
                output += String(parts.hour);
                break;
            case 'h':
                output += pad(hour12);
                break;
            case 'H':
                output += pad(parts.hour);
                break;
            case 'i':
                output += pad(parts.minute);
                break;
            case 's':
                output += pad(parts.second);
                break;
            default:
                output += character;
        }
    }

    return output;
}

function parseIso(iso: string): DateParts | null {
    const match = ISO_PATTERN.exec(iso);

    if (match === null) {
        return null;
    }

    return {
        year: Number(match[1]),
        month: Number(match[2]),
        day: Number(match[3]),
        hour: Number(match[4] ?? 0),
        minute: Number(match[5] ?? 0),
        second: Number(match[6] ?? 0),
    };
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function ordinalSuffix(day: number): string {
    if (day >= 11 && day <= 13) {
        return 'th';
    }

    return ['th', 'st', 'nd', 'rd'][day % 10] ?? 'th';
}

function isLeapYear(year: number): boolean {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}
