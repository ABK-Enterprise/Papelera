<script lang="ts">
    import type { Snippet } from 'svelte';

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
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-primary/10 opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-primary/5 dark:mix-blend-screen"></div>
        <div class="absolute -right-20 top-1/3 h-80 w-80 rounded-full bg-secondary opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-secondary/10 dark:mix-blend-screen"></div>
        <div class="absolute -bottom-20 left-1/3 h-96 w-96 rounded-full bg-accent/30 opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-accent/5 dark:mix-blend-screen"></div>
    </div>

    <!-- Main Content Canvas -->
    <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center justify-center px-4 py-12 md:px-12">
        <div class="w-full max-w-[440px]">
            <div
                class="w-full rounded-3xl border border-border/80 bg-card/90 p-8 shadow-xl backdrop-blur-2xl transition-all sm:p-10 dark:bg-card/85 dark:border-border/60"
            >
                <div class="mb-6 flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-bold tracking-tight leading-tight text-foreground sm:text-3xl">
                        {title}
                    </h1>
                    {#if description}
                        <p class="text-sm text-muted-foreground leading-relaxed">
                            {description}
                        </p>
                    {/if}
                </div>

                {@render children?.()}
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-6 py-6 text-xs text-muted-foreground">
        <span class="tabular-nums">&copy; {new Date().getFullYear()} Todos los derechos reservados.</span>
    </footer>
</div>
