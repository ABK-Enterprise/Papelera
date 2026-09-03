<script lang="ts">
    interface Props {
        text?: string | number | null;
        query?: string | null;
        class?: string;
        highlightClass?: string;
    }

    let {
        text = '',
        query = '',
        class: className = '',
        highlightClass = 'font-bold text-primary underline decoration-primary/40 underline-offset-2',
    }: Props = $props();

    function removeDiacritics(str: string): string {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    interface Segment {
        text: string;
        isMatch: boolean;
    }

    let segments = $derived.by<Segment[]>(() => {
        const rawText = text !== undefined && text !== null ? String(text) : '';
        const rawQuery = (query ?? '').trim();

        if (!rawText || !rawQuery) {
            return [{ text: rawText, isMatch: false }];
        }

        const normalizedText = removeDiacritics(rawText.toLowerCase());
        const normalizedQuery = removeDiacritics(rawQuery.toLowerCase());

        const result: Segment[] = [];
        let startIndex = 0;

        while (startIndex < rawText.length) {
            const matchIndex = normalizedText.indexOf(normalizedQuery, startIndex);
            if (matchIndex === -1) {
                result.push({
                    text: rawText.slice(startIndex),
                    isMatch: false,
                });
                break;
            }

            if (matchIndex > startIndex) {
                result.push({
                    text: rawText.slice(startIndex, matchIndex),
                    isMatch: false,
                });
            }

            const matchLength = rawQuery.length;
            result.push({
                text: rawText.slice(matchIndex, matchIndex + matchLength),
                isMatch: true,
            });

            startIndex = matchIndex + matchLength;
        }

        return result;
    });
</script>

<span class={className}>
    {#each segments as segment}
        {#if segment.isMatch}
            <mark class="bg-transparent {highlightClass}">{segment.text}</mark>
        {:else}
            <span>{segment.text}</span>
        {/if}
    {/each}
</span>
