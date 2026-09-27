<script lang="ts">
    import ImageCropperModal from '@/components/common/ImageCropperModal.svelte';

    let {
        open = $bindable(false),
        imageSrc = null,
        onApply,
        onCancel,
    }: {
        open?: boolean;
        imageSrc?: string | null;
        onApply?: (blob: Blob, url: string) => void;
        onCancel?: () => void;
    } = $props();
</script>

<ImageCropperModal
    bind:open
    {imageSrc}
    cropShape="circle"
    outputFormat="image/png"
    targetSize={512}
    title="Ajustar encuadre del logo"
    description="Arrastrá el logo para centrarlo dentro del círculo y usá el zoom."
    {onApply}
    {onCancel}
/>
