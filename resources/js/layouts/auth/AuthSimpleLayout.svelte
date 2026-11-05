<script lang="ts">
    import type { Snippet } from 'svelte';
    import BorderGlow from '@/components/svelte-bits/BorderGlow.svelte';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="relative flex min-h-screen flex-col justify-between overflow-hidden bg-background text-foreground antialiased selection:bg-primary/20"
>
    <!-- Ambient Background Blobs -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
        <div class="absolute -left-20 top-1/4 h-[480px] w-[480px] rounded-full bg-primary-500/40 blur-[120px] transition-all duration-1000 dark:bg-primary-500/35"></div>
        <div class="absolute -right-20 top-1/3 h-[420px] w-[420px] rounded-full bg-secondary-500/35 blur-[100px] transition-all duration-1000 dark:bg-accent-500/30"></div>
        <div class="absolute -bottom-24 left-1/3 h-[500px] w-[500px] rounded-full bg-accent-500/30 blur-[130px] transition-all duration-1000 dark:bg-primary-600/30"></div>
    </div>

    <!-- Main Content Canvas -->
    <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center justify-center px-4 py-12 md:px-12">
        <div class="w-full max-w-[440px]">
            <BorderGlow
                class="w-full p-8 shadow-xl backdrop-blur-2xl transition-all sm:p-10"
                borderRadius={24}
                edgeSensitivity={30}
                glowRadius={40}
                glowIntensity={1.0}
                coneSpread={25}
                fillOpacity={0.4}
            >
                <div class="mb-6 flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-bold tracking-tight leading-tight text-foreground sm:text-3xl">
                        {#if title === 'Ingresa a ABK Solutions!'}
                            <span class="block sm:inline">Ingresa a</span> <span class="block sm:inline">ABK Solutions!</span>
                        {:else}
                            {title}
                        {/if}
                    </h1>
                    {#if description}
                        <p class="text-sm text-muted-foreground leading-relaxed">
                            {description}
                        </p>
                    {/if}
                </div>

                {@render children?.()}
            </BorderGlow>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-6 py-6 text-xs text-muted-foreground">
        <span class="tabular-nums">&copy; {new Date().getFullYear()} Todos los derechos reservados.</span>
    </footer>
</div>
