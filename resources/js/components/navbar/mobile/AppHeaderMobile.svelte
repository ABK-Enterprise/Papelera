<script lang="ts">
    import Menu from 'lucide-svelte/icons/menu';
    import BookOpen from 'lucide-svelte/icons/book-open';
    import UserAvatarDropdown from '@/components/navbar/UserAvatarDropdown.svelte';
    import UserManualModal from '@/components/manual/UserManualModal.svelte';
    import { page } from '@inertiajs/svelte';

    let {
        onToggleMenu,
    }: {
        onToggleMenu?: () => void;
    } = $props();

    let tenant = $derived(page.props.auth?.tenant);
    let isManualModalOpen = $state(false);
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
        <div class="flex items-center gap-1.5">
            {#if tenant}
                <button
                    type="button"
                    onclick={() => (isManualModalOpen = true)}
                    class="flex h-10 w-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary"
                    aria-label="Abrir manual de usuario"
                    title="Manual de usuario"
                >
                    <BookOpen class="h-5 w-5" />
                </button>
            {/if}
            <UserAvatarDropdown />
        </div>
    </div>
</header>

<UserManualModal bind:isOpen={isManualModalOpen} />
