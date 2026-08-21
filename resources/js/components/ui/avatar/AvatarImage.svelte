<script lang="ts">
    import { cn } from '@/lib/utils';

    let { class: className = '', ...rest } = $props();
</script>

<img
    class={cn('aspect-square h-full w-full object-cover', className)}
    referrerpolicy="no-referrer"
    {...rest}
/>
