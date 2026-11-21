import assert from 'node:assert/strict';
import { test } from 'node:test';
import { getAnchoredPopoverStyle } from '../../resources/js/shared/composables/getAnchoredPopoverStyle.js';

test('anchored admin popovers stay inside mobile, tablet, and desktop viewport edges', () => {
    const originalDocument = globalThis.document;
    const originalWindow = globalThis.window;

    try {
        for (const width of [320, 360, 375, 390, 414, 768, 1280, 1440]) {
            globalThis.document = { documentElement: { clientWidth: width } };
            globalThis.window = {
                innerWidth: width,
                innerHeight: 800,
                visualViewport: { width, height: 800, offsetLeft: 0, offsetTop: 0 },
            };

            for (const anchorLeft of [0, width - 40]) {
                for (const anchorTop of [4, 760]) {
                    const trigger = {
                        getBoundingClientRect: () => ({
                            left: anchorLeft,
                            right: anchorLeft + 36,
                            top: anchorTop,
                            bottom: anchorTop + 36,
                        }),
                    };
                    const style = getAnchoredPopoverStyle(trigger, {
                        width: 360,
                        maxHeight: 520,
                        viewportAware: true,
                    });
                    const left = Number.parseFloat(style.left);
                    const top = Number.parseFloat(style.top);
                    const menuWidth = Number.parseFloat(style.width);
                    const menuHeight = Number.parseFloat(style.maxHeight);

                    assert.equal(style.position, 'fixed');
                    assert.ok(left >= 8, `left gutter at ${width}px`);
                    assert.ok(left + menuWidth <= width - 8, `right gutter at ${width}px`);
                    assert.ok(top >= 8, `top gutter at ${width}px`);
                    assert.ok(top + menuHeight <= 792, `bottom gutter at ${width}px`);
                }
            }
        }
    } finally {
        if (originalDocument === undefined) delete globalThis.document;
        else globalThis.document = originalDocument;
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});
