<template>
    <app-confirm-dialog
        v-if="dialog"
        v-model:dialog="dialog"
        leave-open
        :title="`Update the &quot;${item?.name}&quot; source`"
        :btn-confirm-active="btnConfirmActive"
        @confirmed="onConfirmed"
        @canceled="onCanceled"
        @keyup.esc="onCanceled"
        @keyup.enter="onConfirmed"
    >
        <template #text>
            <source-details-form
                :id="item?.id"
                ref="form"
                @form:done="onFormDone"
                @form:unableLoad="btnConfirmActive = false"
            ></source-details-form>
        </template>
    </app-confirm-dialog>
</template>

<script setup lang="ts">
    import {ref, useTemplateRef} from 'vue';
    import AppConfirmDialog from '../../../../component/general/app-dialog/AppConfirmDialog.vue';
    import SourceInfo from '../../../../model/source/SourceInfo';
    import {useFlashStore} from '../../../../component/general/app-flash/store';
    import SourceDetailsForm from '../form/SourceDetailsForm.vue';

    const props = defineProps({
        item: {
            type: SourceInfo,
            required: true,
        },
    });

    const flashStore = useFlashStore();
    const emit = defineEmits(['confirmed', 'canceled']);
    const dialog = defineModel('dialog', { type: Boolean, default: false });
    const form = useTemplateRef('form');

    const btnConfirmActive = ref(true);

    const onConfirmed = () => {
        form.value?.onSubmit();
    };

    const onFormDone = () => {
        flashStore.addSuccess({text:'Source was successfully created'});
        emit('confirmed');
    };

    const onCanceled = () => {
        emit('canceled');
    };
</script>
