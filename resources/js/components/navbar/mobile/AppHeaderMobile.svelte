<script lang="ts">
    import Menu from 'lucide-svelte/icons/menu';
    import UserAvatarDropdown from '@/components/navbar/UserAvatarDropdown.svelte';

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

        <!-- Centro: Espacio reservado para balance flex -->
        <div class="flex items-center justify-center"></div>

        <!-- Extremo Derecho: Avatar de Usuario con Menú -->
        <div class="flex items-center">
            <UserAvatarDropdown />
        </div>
    </div>
</header>
