<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import Menu from 'lucide-svelte/icons/menu';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import UserAvatarDropdown from '@/components/navbar/UserAvatarDropdown.svelte';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';

    let {
        onToggleMenu,
    }: {
        onToggleMenu?: () => void;
    } = $props();
</script>

<header class="w-full border-b border-border/40 bg-background/95 backdrop-blur shadow-sm">
    <div class="flex h-14 items-center justify-between px-4">
        <!-- Extremo Izquierdo: Botón Hamburguesa -->
        <button
            type="button"
            onclick={onToggleMenu}
            class="flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
            aria-label="Abrir menú de navegación"
        >
            <Menu class="h-6 w-6" />
        </button>

        <!-- Centro: Isotipo de la Aplicación -->
        <Link
            href={toUrl(dashboard())}
            class="flex items-center justify-center rounded-lg p-1 transition-transform active:scale-95"
            aria-label="Dashboard"
        >
            <AppLogoIcon class="h-7 w-7 text-primary fill-current" />
        </Link>

        <!-- Extremo Derecho: Avatar de Usuario con Menú -->
        <div class="flex items-center">
            <UserAvatarDropdown />
        </div>
    </div>
</header>
