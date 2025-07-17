<template>
    <AuthenticatedLayout >
        <nav class="flex items-center justify-end p-1 mb-3">
            <div>
                <DeleteForeverButton :all-selected="allSelected" :selected-ids="selectedIds" @delete="resetForm" />
                <RestoreFilesButton :all-selected="allSelected" :selected-ids="selectedIds" @restore="resetForm" />
            </div>
        </nav>
        <div class="flex-1 overflow-auto">
            <table class="min-w-full">
                    <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left w-[30px] max-w-[30px] pr-0">
                            <Checkbox @change="onSelectAllChange" v-model:checked="allSelected" />
                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Name
                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Path
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="file of allFiles.data" :key="file.id"
                        @click="$event => toggleFileSelect(file)"
                        class="bg-white border-b transition duration-300 ease-in-out
                                hover:bg-blue-100 cursor-pointer"
                        :class="(selected[file.id] || allSelected) ? 'bg-blue-50' : 'bg-white'">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 w-[30px] max-w-[30px] pr-0">
                             <Checkbox @change="onSelectCheckboxChange(file)" v-model="selected[file.id]" :checked="selected[file.id] || allSelected" /> <!--se selected è vuoto, usiamo allSelected-->
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 flex items-center">
                            <FileIcon :file="file" />
                            {{ file.name}}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ file.path }}
                        </td>
                    </tr>
                    </tbody>
            </table>

            <!--se non ci sono file (lunghezza zero), mostra questo-->
            <div v-if="!allFiles.data.length" class="py-8 text-center text-lg text-gray-400">
                There is no data in this folder
            </div>
            <!--elemento DOM in fondo alla pagina, quando entra nella viewport, con IntersectionObserver si agisce-->
            <div ref="loadMoreIntersect"></div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
//Imports
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileIcon from '@/Components/app/FileIcon.vue';
import { computed, onMounted, onUpdated, ref } from 'vue';
import { httpGet } from '@/Helper/http-helper';
import Checkbox from '@/Components/Checkbox.vue';
import RestoreFilesButton from '@/Components/app/RestoreFilesButton.vue';
import DeleteForeverButton from '@/Components/app/DeleteForeverButton.vue';


//Props and Emit
const props = defineProps({
    files: Object,
    folder: Object,
    ancestors: Object
})

//Computed
                 // la nostra funzione computed estrae gli id dei file selezionati
                                // Object.entries(selected.value) trasforma l'oggetto in un array di coppie chiave->valore
                                // (da dizionario a array di array/tuple con chiave->valore)
                                                               // .filter(a => a[1]) filtra solo quelli con valore true
                                                               // a => a[1] indica il secondo elemento della tupla, ovvero il valore
                                                                                // .map(a => a[0]) estrae solo le chiavi (id)
                                                                                // a => a[0] indica il primo elemento, ovvero la chiave
const selectedIds = computed(() => Object.entries(selected.value).filter(a => a[1]).map(a => a[0]))


//Refs
const allSelected = ref(false) // vero se selezionata (e selezioniamo tutto) oppure falso
const selected = ref({}) // se selezioniamo un file, inseriamo il suo id in questa ref, associato al valore true (o false se deselzioniamo)

const loadMoreIntersect = ref(null) //elemento dom che osserviamo con IntersectionObserver

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next
})

//Methods
function loadMore()
{
    //se non abbiamo una seconda pagina da caricare, return e non facciamo nulla
    if (allFiles.value.next === null) {
        return
    }

    // altrimenti mandiamo una richiesta http alla nuova pagina con i nuovi dati
    // e aggiorna allFiles.data con i dati precedenti e quelli ricevuti ora
    // e aggiorna anche il link all'eventuale ulteriore pagina se ce n'è una, altrimenti lo imposta a null
    httpGet(allFiles.value.next)
        .then(res => {
            allFiles.value.data = [...allFiles.value.data, ...res.data]
            allFiles.value.next = res.links.next
        })
}

function onSelectAllChange(){
    // iterando su tutti i file che abbiamo, impostiamo il valore del loro selected uguale a quello di allSelected
    allFiles.value.data.forEach(f => {
        selected.value[f.id] = allSelected.value
    })
}

function toggleFileSelect(file) {
    // cambiamo lo stato attuale della checkbox del file (collegato a @click sulla riga del file)
    selected.value[file.id] = !selected.value[file.id]
    // controlliamo anche al click sulla riga se sia opportuno depuntare o spuntare la checkbox di allSelected
    onSelectCheckboxChange(file)
}

function onSelectCheckboxChange(file){
    if (!selected.value[file.id]) { // se il file corrente non è selezionato, allora sicuramente allSelected deve essere false
        allSelected.value = false
    } else { //altrimenti:
        let checked = true //impostiamo una flag di controllo a true

        for (let file of allFiles.value.data) { // per ogni file di allFiles.value.data
            if (!selected.value[file.id]){      // se non è selezionato mettiamo la flag a false e breakiamo il ciclo
                checked = false
                break
            }
        }

        allSelected.value = checked    // dopo tutti i controlli assegnamo a allSelected il valroe della flag di controllo
    }
}

function resetForm() {
    allSelected.value = false
    selected.value = {}
}


//Hooks
onUpdated( () => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next
    }
})

onMounted(() => {
    //ongi volta che il tergetElement cambia l asua visibilità si chiama l'azione di callback
    // const observer = new IntersectionObserver(callback, options)
    const observer = new IntersectionObserver( (entries) => entries.forEach(entry => entry.isIntersecting && loadMore()), {
        rootMargin: '-250px 0px 0px 0px'
    })

    // observer.observe(targetElement)
    observer.observe(loadMoreIntersect.value)
})


</script>


