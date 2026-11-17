import { describe, expect, it } from 'vite-plus/test';
import { motionTransitions, transitionStyle } from './motion';

describe('transitionStyle', () => {
    it('emits the named transition as an inline style', () => {
        expect(transitionStyle('rise')).toEqual({
            transitionProperty: 'opacity, transform',
            transitionDuration: '200ms',
            transitionTimingFunction: motionTransitions.rise.easing,
        });
    });

    it('animates only composited properties', () => {
        for (const name of ['fade', 'rise', 'drawer', 'pop'] as const) {
            expect(transitionStyle(name).transitionProperty).toMatch(
                /^(opacity|transform)(, (opacity|transform))?$/,
            );
        }
    });
});
