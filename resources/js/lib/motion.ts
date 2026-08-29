import type { CSSProperties } from 'react';

/**
 * The named transitions this application animates with.
 *
 * Only `transform` and `opacity` are ever animated. Both are composited, so
 * they cost no layout and no paint; animating a box's size or position instead
 * makes the browser re-lay-out the page on every frame, which is where dropped
 * frames come from on a long list.
 *
 * Reduced motion is answered by removing the animation, not by shortening it. A
 * quicker slide is still a slide, and the setting is a request for no movement
 * at all, so every transition emits a zero duration when it is on. The same
 * promise is made for CSS-driven animation in `resources/css/app.css`.
 */
type Transition = {
    /** Milliseconds. Zero means the change is applied on the spot. */
    duration: number;
    easing: string;
    /** Composited properties only. */
    animates: readonly ('transform' | 'opacity')[];
};

const EASING = 'cubic-bezier(0.2, 0, 0, 1)';

export const motionTransitions = {
    fade: { duration: 150, easing: EASING, animates: ['opacity'] },
    rise: { duration: 200, easing: EASING, animates: ['opacity', 'transform'] },
    drawer: { duration: 250, easing: EASING, animates: ['transform'] },
    pop: { duration: 120, easing: EASING, animates: ['opacity', 'transform'] },
} as const satisfies Record<string, Transition>;

export type TransitionName = keyof typeof motionTransitions;

/**
 * Read at call time rather than at import time, so a preference changed after
 * the bundle loaded is still honoured on the next transition.
 */
function prefersReducedMotion(): boolean {
    return (
        globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ===
        true
    );
}

/** The inline style for a named transition, zeroed under reduced motion. */
export function transitionStyle(name: TransitionName): CSSProperties {
    const transition = motionTransitions[name];

    return {
        transitionProperty: transition.animates.join(', '),
        transitionDuration: `${prefersReducedMotion() ? 0 : transition.duration}ms`,
        transitionTimingFunction: transition.easing,
    };
}
