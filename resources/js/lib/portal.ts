/**
 * Svelte action that mounts the element directly to `document.body` (or a target selector/element),
 * breaking out of parent stacking contexts (`sticky`, `transform`, `filter`, `z-index`) so modals
 * and backdrops cover the entire viewport and block pointer events underneath.
 */
export function portal(node: HTMLElement, target: HTMLElement | string = 'body') {
    let targetEl: HTMLElement | null = null;

    function update(newTarget: HTMLElement | string) {
        if (typeof document === 'undefined') return;
        targetEl =
            typeof newTarget === 'string'
                ? document.querySelector<HTMLElement>(newTarget) ?? document.body
                : newTarget;
        targetEl.appendChild(node);
    }

    update(target);

    return {
        update,
        destroy() {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
        },
    };
}
