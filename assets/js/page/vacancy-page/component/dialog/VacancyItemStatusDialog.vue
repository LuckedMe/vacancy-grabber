<template>
    <app-confirm-dialog
        v-if="dialog"
        v-model:dialog="dialog"
        leave-open
        title="Update status"
        @confirmed="onConfirmed"
        @canceled="onCanceled"
        @keyup.esc="onCanceled"
        @keyup.enter="onConfirmed"
    >
        <template #text>
            <v-card-text>
                Update vacancy status
            </v-card-text>
            <vacancy-item-status-form
                :id="item.id"
                ref="form"
                @form:done="onFormDone"
            ></vacancy-item-status-form>
        </template>
    </app-confirm-dialog>
</template>

<script setup lang="ts">
    import AppConfirmDialog from '../../../../component/general/app-dialog/AppConfirmDialog.vue';
    import VacancyInfo from '../../../../model/vacancy/VacancyInfo';
    import {useFlashStore} from '../../../../component/general/app-flash/store';
    import {useTemplateRef} from 'vue';
    import VacancyItemStatusForm from '../form/VacancyItemStatusForm.vue';

    defineProps({
        item: {
            type: VacancyInfo,
            required: true,
        },
    });

    const flashStore = useFlashStore();
    const emit = defineEmits(['confirmed', 'canceled']);
    const dialog = defineModel('dialog', { type: Boolean, default: false });
    const form = useTemplateRef('form');

    const onConfirmed = () => {
        form.value?.onSubmit();
    };

    const onFormDone = () => {
        flashStore.addSuccess({text:'Vacancy was successfully updated'});
        emit('confirmed');
    };

    const onCanceled = () => {
        emit('canceled');
    };
</script>
