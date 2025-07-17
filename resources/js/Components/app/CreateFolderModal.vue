<template>
    <modal :show="modelValue" @show="onShow" max-width="sm">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Create New Folder
            </h2>
        </div>
        <div class="mt-6 p-3">
            <InputLabel for="folderName" value="Folder Name" class="sr-only"/>
            <TextInput type="text"
                        ref="folderNameInput"
                        id="folderName" v-model="form.name"
                        class="mt-1 block w-full"
                        :class="form.errors.name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                        placeholder="Folder Name"
                        @keyup.enter="createFolder"
            />
            <InputError :message="form.errors.name" class="mt-2"/>

        </div>
        <div class="mt-6 flex justify-end p-3">
            <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
            <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }"
                            @click="createFolder"
                            :disable="form.processing">
                Submit
            </PrimaryButton>
        </div>
    </modal>
</template>

<script setup>
//Imports
import Modal from "@/Components/Modal.vue"
import InputLabel from "../InputLabel.vue";
import TextInput from "../TextInput.vue";
import { useForm, usePage } from "@inertiajs/vue3";
import InputError from "../InputError.vue";
import SecondaryButton from "../SecondaryButton.vue";
import PrimaryButton from "../PrimaryButton.vue";
import { nextTick, ref } from "vue";
import { showSuccessNotification } from "@/event-bus";

//Uses
const form = useForm({
    name: '',
    parent_id: null
})
const page = usePage();


//Refs
const folderNameInput = ref(null)


//Props & Emit
const {modelValue} = defineProps({
    modelValue: Boolean
})
const emit = defineEmits(['update:modelValue'])

//Methods
function onShow(){
    nextTick(() => folderNameInput.value.focus())
}

function createFolder(){
    form.parent_id = page.props.folder.id
    form.post(route('folder.create'), {
        preserveScroll: true,
        onSuccess: () => {
            const folderName = form.name
            closeModal()
            showSuccessNotification(`The folder "${folderName}" was created`)
            form.reset();
        },
        // Se ci sono errori di validazione alla risposta del server, allora esegui la funzione che mette il focus sull’input folderNameInput.
        onError : () => folderNameInput.value.focus()
    })
}

function closeModal(){
    emit('update:modelValue');
    form.clearErrors();
    form.reset();
}

</script>
