<template>
    <modal :show="props.modelValue" @show="onShow" max-width="sm">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Share Files
            </h2>
        </div>
        <div class="mt-6 p-3">
            <InputLabel for="shareEmail" value="Enter Email Address" class="sr-only"/>
            <TextInput type="text"
                        ref="emailInput"
                        id="shareEmail"
                        v-model="form.email"
                        class="mt-1 block w-full"
                        :class="form.errors.email ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''"
                        placeholder="Enter Email Address"
                        @keyup.enter="share"
            />
            <InputError :message="form.errors.email" class="mt-2" />

        </div>
        <div class="mt-6 flex justify-end p-3">
            <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
            <PrimaryButton class="ml-3" :class="{ 'opacity-25': form.processing }"
                            @click="share"
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
import SecondaryButton from "../SecondaryButton.vue";
import PrimaryButton from "../PrimaryButton.vue";
import { nextTick, ref} from "vue";
import InputError from "../InputError.vue";
import { showSuccessNotification } from "@/event-bus";

//Uses
const form = useForm({
    email: null,
    ids : [],
    all: false,
    parent_id: null
})
const page = usePage();


//Refs
const emailInput = ref(null)


//Props & Emit
const props = defineProps({
    modelValue: Boolean,
    allSelected: Boolean,
    selectedIds: Array
})
const emit = defineEmits(['update:modelValue'])

//Methods
function onShow(){
    nextTick(() => emailInput.value.focus())
}

function share(){
    form.parent_id = page.props.folder.id
    console.log(props.selectedIds, props.allSelected)
    if (props.allSelected) {
        form.all = true
        form.ids = []
    } else {
        form.ids = props.selectedIds
    }
    const email = form.email
    form.post(route('file.share'), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            form.reset();
            showSuccessNotification(`Selected files will be shared to "${email}" if the emails exists in the system`)
        },
        // Se ci sono errori di validazione alla risposta del server, allora esegui la funzione che mette il focus sull’input emailInput.
        onError : () => emailInput.value.focus()
    })
}

function closeModal(){
    emit('update:modelValue');
    form.clearErrors();
    form.reset();
}


</script>
