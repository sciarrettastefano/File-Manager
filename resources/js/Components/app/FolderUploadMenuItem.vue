<template>
    <MenuItem v-slot="{ active }">
        <a href="#" class="relative text-gray-700 block px-4 py-2 text-sm">
            Upload Folder
            <input @change="onChange" type="file" class="absolute
                top-0 bottom-0 right-0 cursor-pointer opacity-0" multiple
                directory webkitdirectory>
        </a>
    </MenuItem>
</template>

<script setup>
//Imports
import { emitter, FILE_UPLOAD_STARTED } from '@/event-bus';
import { MenuItem } from '@headlessui/vue';

//Props & Emits

//Methods
function onChange(event){
    // passo come chiave FILE_UPLOAD_STARTED e passo come parametro i file che vanno caricati
    emitter.emit(FILE_UPLOAD_STARTED, event.target.files)
}


</script>
