<template>
    <app-confirm-dialog
        v-if="dialog"
        v-model:dialog="dialog"
        leave-open
        title="Create Vacancy Notes"
        :btn-confirm-active="btnConfirmActive"
        min-width="700"
        min-height="500"
        @confirmed="onConfirmed"
        @canceled="onCanceled"
        @keyup.esc="onCanceled"
        @keyup.enter.prevent
    >
        <template #text>
            <vacancy-item-note-form
                :id="item?.id as string"
                ref="form"
                @form:done="onFormDone"
                @form:unable-load="btnConfirmActive = false"
            ></vacancy-item-note-form>
        </template>
    </app-confirm-dialog>
</template>

<script setup lang="ts">
    import {ref, useTemplateRef} from 'vue';
    import AppConfirmDialog from '../../../../component/general/app-dialog/AppConfirmDialog.vue';
    import VacancyInfo from '../../../../model/vacancy/VacancyInfo';
    import {useFlashStore} from '../../../../component/general/app-flash/store';
    import VacancyItemNoteForm from '../form/VacancyItemNoteForm.vue';

    defineProps({
        item: {
            type: VacancyInfo,
            required: true,
        },
    });

    const flashStore = useFlashStore();
    const emit = defineEmits(['confirmed', 'canceled']);
    const dialog = defineModel('dialog', {type: Boolean, default: false});
    const form = useTemplateRef('form');

    const btnConfirmActive = ref(true);

    const onConfirmed = () => {
        form.value?.onSubmit();
    };

    const onFormDone = () => {
        flashStore.addSuccess({text: 'Vacancy was successfully updated'});
        emit('confirmed');
    };

    const onCanceled = () => {
        emit('canceled');
    };
</script>
