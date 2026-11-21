export function getAnchoredPopoverStyle(trigger, {
    width: preferredWidth,
    maxHeight: preferredHeight,
    gutter = 8,
    gap = 10,
    alignment = 'end',
    viewportAware = false,
} = {}) {
    if (!trigger) return {};

    const visualViewport = viewportAware ? window.visualViewport : null;
    const viewportLeft = visualViewport?.offsetLeft ?? 0;
    const viewportTop = visualViewport?.offsetTop ?? 0;
    const viewportWidth = visualViewport?.width ?? document.documentElement.clientWidth;
    const viewportHeight = visualViewport?.height ?? window.innerHeight;
    const triggerRect = trigger.getBoundingClientRect();
    const width = Math.max(0, Math.min(preferredWidth, viewportWidth - gutter * 2));
    const minLeft = viewportLeft + gutter;
    const maxLeft = Math.max(minLeft, viewportLeft + viewportWidth - gutter - width);
    const preferredLeft = alignment === 'start' ? triggerRect.left : triggerRect.right - width;
    const left = Math.min(maxLeft, Math.max(minLeft, preferredLeft));
    const viewportBottom = viewportTop + viewportHeight;
    const belowTop = Math.max(viewportTop + gutter, triggerRect.bottom + gap);
    const belowSpace = Math.max(0, viewportBottom - gutter - belowTop);
    const aboveSpace = Math.max(0, triggerRect.top - gap - (viewportTop + gutter));
    const placeAbove = belowSpace < Math.min(preferredHeight, 180) && aboveSpace > belowSpace;
    const availableHeight = Math.min(preferredHeight, placeAbove ? aboveSpace : belowSpace);
    const top = placeAbove ? triggerRect.top - gap - availableHeight : belowTop;

    return {
        position: 'fixed',
        top: `${Math.max(viewportTop + gutter, Math.min(top, viewportBottom - gutter - availableHeight))}px`,
        right: 'auto',
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: `${availableHeight}px`,
    };
}
