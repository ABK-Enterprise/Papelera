<script lang="ts">
    import {
        Avatar,
        AvatarFallback,
        AvatarImage,
    } from '@/components/ui/avatar';
    import { getInitials } from '@/lib/initials';
    import { cn } from '@/lib/utils';
    import type { User } from '@/types';

    let {
        user,
        showEmail = false,
        class: className = '',
    }: {
        user: User;
        showEmail?: boolean;
        class?: string;
    } = $props();

    let imageFailed = $state(false);
    const hasAvatar = $derived(Boolean(user?.avatar && !imageFailed));
</script>

<div class={cn('flex items-center gap-3 min-w-0', className)}>
    <Avatar class="h-9 w-9 shrink-0 overflow-hidden rounded-lg border border-sidebar-border/60">
        {#if hasAvatar}
            <AvatarImage
                src={user.avatar!}
                alt={user.name}
                onerror={() => (imageFailed = true)}
            />
        {/if}
        <AvatarFallback class="rounded-lg bg-primary/10 text-xs font-semibold text-primary dark:bg-primary/20 dark:text-primary-foreground">
            {getInitials(user?.name)}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight min-w-0">
        <span class="truncate font-medium text-foreground">{user?.name}</span>
        {#if showEmail}
            <span class="truncate text-xs text-muted-foreground">{user?.email}</span>
        {/if}
    </div>
</div>

