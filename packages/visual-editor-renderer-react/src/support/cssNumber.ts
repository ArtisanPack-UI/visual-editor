/**
 * Number formatting for CSS values (R-13).
 *
 * Byte-for-byte twin of the Blade renderer's `BlockSupports::cssNumber()`:
 * PHP's own float-to-string conversion (14 significant digits) is kept,
 * except where PHP switches to exponent notation (`1.0E-8`, `1.0E+14`),
 * which CSS doesn't parse. There, tiny values are written to four decimals
 * (so `1e-8` becomes `0`) and huge ones as whole numbers.
 *
 * @since 1.15.0
 */

import { phpNumberFormat } from './phpNumberFormat';

/**
 * Format `value` the way `BlockSupports::cssNumber()` does.
 */
export function cssNumber(value: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    const rounded = Number(value.toPrecision(14));
    const magnitude = Math.abs(rounded);

    // PHP's `%.14G` uses exponent notation below 1e-4 and from 1e14 on.
    if (magnitude === 0 || (magnitude >= 1e-4 && magnitude < 1e14)) {
        return String(rounded);
    }

    if (Math.abs(value) >= 1) {
        return BigInt(Math.round(value)).toString();
    }

    const fixed = phpNumberFormat(value, 4).replace(/0+$/, '').replace(/\.$/, '');

    return fixed === '' || fixed === '-0' ? '0' : fixed;
}
