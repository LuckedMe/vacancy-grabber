<template>
    <v-card-text v-if="alertStore.items" class="alert-container">
        <v-alert
            v-for="(item, key) in alertStore.items"
            :key="key"
            :color="item.color"
            :icon="item.icon as string"
            :title="item.title"
            :text="item.text"
            :closable="item.closable as boolean"
            class="mb-2"
            variant="tonal"
        ></v-alert>
    </v-card-text>

    <v-card-text v-if="props.example" class="mt-5">
        <v-divider class="my-2"></v-divider>

        <div>Alert:</div>
        <v-btn text="Clear" @click="alertStore.clear()"></v-btn>
        <v-btn color="default" text="add" @click="alertStore.add({title: 'title', text: 'text'})"></v-btn>
        <v-btn color="info" text="add Info" @click="alertStore.addInfo({title: 'title', text: 'text'})"></v-btn>
        <v-btn color="success" text="add Success" @click="alertStore.addSuccess({title: 'title', text: 'text'})"></v-btn>
        <v-btn color="warning" text="add Warning" @click="alertStore.addWarning({title: 'title', text: 'text'})"></v-btn>
        <v-btn color="error" text="add Error" @click="alertStore.addError({title: 'title', text: 'text'})"></v-btn>

        <v-divider class="my-2"></v-divider>

        items: {{ alertStore.items }}
        <v-divider class="my-2"></v-divider>
    </v-card-text>
</template>

<script setup lang="ts">
    import {useAlertStore} from './store';

    const props = defineProps({
        example: {type: Boolean, default: false},
    });

    const alertStore = useAlertStore();

</script>

<!--<style scoped>-->
<!--    .alert-container {-->
<!--        position: fixed;-->
<!--        top: 20px;-->
<!--        right: 20px;-->
<!--        z-index: 9999;-->
<!--        width: 350px;-->
<!--    }-->
<!--</style>-->
