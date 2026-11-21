<template>
    <div
        v-html="htmlContent"
        :style="style"
    />
</template>

<script setup lang="ts">
    import {nl2br} from "../../utils/HtmlUtils";
    import { computed, type CSSProperties } from "vue";

    const props = defineProps({
        html: {type: [String, null], required: false, default: null},
        noNl2br: {type: [Boolean], required: false, default: false},
        maxWidth: {type: [String, Number, null], default: '600px'},
        maxHeight: {type: [String, Number, null], default: '300px'},
    });

    const style = computed<CSSProperties>(() => {
        return {
            'max-width': props.maxWidth ?? undefined,
            'max-height': props.maxHeight ?? undefined,
            'overflow-y': 'auto',
            'overflow-wrap': 'break-word',
            'white-space': 'normal',
        };
    });

    const htmlContent = computed(() => {
        return props.noNl2br
            ? props.html
            : nl2br(props.html);
    });

</script>
