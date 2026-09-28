import type { NextOccasion } from '@/types';

/**
 * A friendly countdown for an occasion: "Birthday is today", "Christmas in 12 days".
 */
export function describeOccasion(occasion: NextOccasion): string {
    if (occasion.days_until === 0) {
        return `${occasion.name} is today`;
    }

    if (occasion.days_until === 1) {
        return `${occasion.name} is tomorrow`;
    }

    return `${occasion.name} in ${occasion.days_until} days`;
}
