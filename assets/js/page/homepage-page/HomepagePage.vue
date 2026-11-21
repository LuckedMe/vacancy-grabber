<template>
    <v-sheet
        v-if="page?.content"
        class="pa-4 text-center mx-auto"
        elevation="4"
        max-width="600"
        rounded="lg"
        width="100%"
    >
        <app-html
            :html="page?.content"
            no-nl2br
            :max-width="null"
            :max-height="null"
        ></app-html>
    </v-sheet>
</template>

<script setup lang="ts">
    import Page from '../../model/page/Page';
    import AppHtml from '../../component/general/AppHtml.vue';

    defineProps({
        page: {
            type: Page,
            required: false,
            default: () => null,
        },
    });

</script>
