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
    class="relative flex min-h-screen flex-col justify-between overflow-hidden bg-[#fffdf0] text-[#1a1c1c] antialiased selection:bg-[#e1e1f5] dark:bg-[#121212] dark:text-[#f1f1f1]"
    style="background-color: #fffdf0; background-image: radial-gradient(circle at 15% 50%, rgba(225, 225, 245, 0.45), transparent 40%), radial-gradient(circle at 85% 30%, rgba(212, 230, 229, 0.45), transparent 40%); background-attachment: fixed;"
>
    <!-- Abstract Ambient Background Blobs -->
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden">
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-[#e1e1f5] opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-purple-950/20 dark:mix-blend-screen"></div>
        <div class="absolute -right-20 top-1/3 h-80 w-80 rounded-full bg-[#d4e6e5] opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-teal-950/20 dark:mix-blend-screen"></div>
        <div class="absolute -bottom-20 left-1/3 h-96 w-96 rounded-full bg-[#primary-fixed-dim] bg-[#e4e3d6] opacity-40 blur-3xl mix-blend-multiply transition-all duration-1000 dark:bg-yellow-950/10 dark:mix-blend-screen"></div>
    </div>

    <!-- Main Content Canvas -->
    <main class="relative z-10 mx-auto flex w-full max-w-7xl flex-1 items-center justify-center px-4 py-12 md:px-12">
        <div class="w-full max-w-[440px]">
            <div
                class="w-full rounded-2xl border border-white/70 bg-[#fffdf0]/90 p-8 shadow-xl backdrop-blur-2xl transition-all sm:p-10 dark:border-[#e4e3d6]/30 dark:bg-[#fffdf0]/85 dark:shadow-2xl"
                style="box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);"
            >
                <div class="mb-6 flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight text-[#1a1c1c] sm:text-3xl">
                        {title}
                    </h1>
                    {#if description}
                        <p class="text-sm text-[#5f5f55]">
                            {description}
                        </p>
                    {/if}
                </div>

                {@render children?.()}
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-center px-6 py-6 text-xs text-[#78776f] dark:text-muted-foreground">
        <span>&copy; {new Date().getFullYear()} Todos los derechos reservados.</span>
    </footer>
</div>
