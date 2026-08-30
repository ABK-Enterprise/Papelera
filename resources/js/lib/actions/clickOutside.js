/**
 * Svelte action to detect clicks/taps outside of a node.
 * 
 * Usage:
 *   <div use:clickOutside={() => isOpen = false}>...</div>
 *   or
 *   <div use:clickOutside={{ callback: () => isOpen = false, enabled: isOpen }}>...</div>
 */
export function clickOutside(node, options = {}) {
    let callback = typeof options === 'function' ? options : options.callback;
    let enabled = typeof options === 'object' && 'enabled' in options ? options.enabled : true;

    function handlePointerDown(event) {
        if (!enabled) return;
        if (node && !node.contains(event.target) && !event.defaultPrevented) {
            if (typeof callback === 'function') {
                callback(event);
            }
            node.dispatchEvent(new CustomEvent('click_outside', { detail: { target: event.target } }));
        }
    }

    document.addEventListener('pointerdown', handlePointerDown, true);

    return {
        update(newOptions) {
            callback = typeof newOptions === 'function' ? newOptions : newOptions?.callback;
            enabled = typeof newOptions === 'object' && 'enabled' in newOptions ? newOptions.enabled : true;
        },
        destroy() {
            document.removeEventListener('pointerdown', handlePointerDown, true);
        }
    };
}
