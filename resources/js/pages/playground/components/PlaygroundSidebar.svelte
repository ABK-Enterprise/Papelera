<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    interface Props {
        activeTab: 'pos' | 'ptos_venta' | 'wsaa' | 'docs';
        onSelectTab: (tab: 'pos' | 'ptos_venta' | 'wsaa' | 'docs') => void;
        ticket: {
            token?: string;
            sign?: string;
            cuit?: number;
            expiration_time?: string;
            is_valid?: boolean;
        } | null;
        timeRemaining?: string;
        currentPtoVta?: number;
    }

    let { activeTab, onSelectTab, ticket, timeRemaining = '', currentPtoVta = 1 }: Props = $props();

    let isConnected = $derived(ticket && ticket.is_valid && timeRemaining !== 'Expirado');
</script>

<aside class="flex w-64 flex-shrink-0 flex-col border-r border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <!-- Brand -->
    <div class="flex items-center gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm dark:bg-emerald-500 dark:text-zinc-950">
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-black tracking-tight text-zinc-900 dark:text-zinc-100">Papelera POS</h2>
            <span class="inline-block rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                Pto Vta: {String(currentPtoVta).padStart(4, '0')}
            </span>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="flex-1 space-y-1 p-3">
        <!-- Punto de Venta (POS / Emisión) -->
        <button
            type="button"
            onclick={() => onSelectTab('pos')}
            class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-xs font-semibold transition-all {activeTab === 'pos'
                ? 'bg-emerald-50 text-emerald-700 shadow-sm dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'}"
        >
            <div class="flex items-center gap-2.5">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span>Punto de Venta (POS)</span>
            </div>
            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">
                Emisión
            </span>
        </button>

        <!-- Gestión de Puntos de Venta -->
        <button
            type="button"
            onclick={() => onSelectTab('ptos_venta')}
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-xs font-semibold transition-all {activeTab === 'ptos_venta'
                ? 'bg-emerald-50 text-emerald-700 shadow-sm dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'}"
        >
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Puntos de Venta</span>
        </button>

        <!-- Panel WSAA -->
        <button
            type="button"
            onclick={() => onSelectTab('wsaa')}
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-xs font-semibold transition-all {activeTab === 'wsaa'
                ? 'bg-emerald-50 text-emerald-700 shadow-sm dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'}"
        >
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="9"></rect>
                <rect x="14" y="3" width="7" height="5"></rect>
                <rect x="14" y="12" width="7" height="9"></rect>
                <rect x="3" y="16" width="7" height="5"></rect>
            </svg>
            <span>Panel WSAA & Token</span>
        </button>

        <!-- Certificados & Docs -->
        <button
            type="button"
            onclick={() => onSelectTab('docs')}
            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-xs font-semibold transition-all {activeTab === 'docs'
                ? 'bg-emerald-50 text-emerald-700 shadow-sm dark:bg-emerald-950/60 dark:text-emerald-300'
                : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'}"
        >
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>Certificados & RG 5616</span>
        </button>
    </nav>

    <!-- Footer Sidebar -->
    <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                {#if isConnected}
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                {:else}
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                {/if}
            </span>
            <span class="text-xs font-bold {isConnected ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'}">
                {isConnected ? 'ARCA Conectado' : 'Esperando Ticket'}
            </span>
        </div>

        {#if timeRemaining}
            <p class="mt-1 text-[11px] font-mono text-zinc-500 dark:text-zinc-400">
                Vence en: <strong class="text-zinc-800 dark:text-zinc-200">{timeRemaining}</strong>
            </p>
        {/if}

        <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-2 text-[11px] text-zinc-400 dark:border-zinc-800">
            <Link href="/" class="hover:text-zinc-700 hover:underline dark:hover:text-zinc-200">
                ← Volver al Inicio
            </Link>
            <span class="font-mono text-[10px]">Laravel + Svelte 5</span>
        </div>
    </div>
</aside>
