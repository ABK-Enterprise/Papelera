import { cn } from '@/lib/utils';

export { default as NavigationMenu } from './NavigationMenu.svelte';
export { default as NavigationMenuContent } from './NavigationMenuContent.svelte';
export { default as NavigationMenuItem } from './NavigationMenuItem.svelte';
export { default as NavigationMenuLink } from './NavigationMenuLink.svelte';
export { default as NavigationMenuList } from './NavigationMenuList.svelte';
export { default as NavigationMenuTrigger } from './NavigationMenuTrigger.svelte';
export { default as NavigationMenuViewport } from './NavigationMenuViewport.svelte';

export function navigationMenuTriggerStyle(className = '') {
    return cn(
        'inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring hover:bg-primary/10 hover:text-primary dark:hover:bg-primary/15 data-[state=open]:bg-primary/10 data-[state=open]:text-primary dark:data-[state=open]:bg-primary/15',
        className,
    );
}
