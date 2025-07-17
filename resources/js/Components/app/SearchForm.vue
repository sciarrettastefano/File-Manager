<template>
    <div class="w-[600px] h-[80px] flex items-center" action="">
        <TextInput type="text"
                    class="block w-full mr-2"
                    v-model="search"
                    autocomplete="on"
                    @keyup.enter.prevent="onSearch"
                    placeholder="Search for files and folders"/>
    </div>
</template>

<script setup>
//Imports
import TextInput from "../TextInput.vue";
import { onMounted, ref } from "vue";
import {router} from "@inertiajs/vue3";
import { emitter, ON_SEARCH } from "@/event-bus";

//Uses
let params = '' // definendolo qua ed in onMounted (fuori da onSearch) mi mantiene ciò che ho cercato

//Refs
const search = ref('')

//Methods
function onSearch() {
    params.set('search', search.value)
    router.get(window.location.pathname + '?' + params.toString())

    emitter.emit(ON_SEARCH, search.value)
}

//Hooks
onMounted( () => {
    params = new URLSearchParams(window.location.search)
    // aveveo un warning per errore del tipo di search
    // => perchè qui sotto get poteva trovare un null, ma non poteva reimpostare il search.value
    //    proprio a null, poichè search.value vuole un intero
    // risolto quindi assegnando a search.value:
    //                              - se esiste, il params.get('search')
    //                              - sennò una stringa vuota
    search.value = params.get('search') ?? ''
})


</script>
