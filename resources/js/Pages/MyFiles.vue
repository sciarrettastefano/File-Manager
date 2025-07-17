<template>
    <AuthenticatedLayout >
        <nav class="flex items-center justify-between p-1 mb-3"> <!--breadcrumbs-->
            <ol class="flex items-center space-x-1 md:space-x-3">
                <li v-for="ans of ancestors.data" :key="ans.id" class="inline-flex items-center">
                    <Link v-if="!ans.parent_id" :href="route('myFiles')"
                            class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 ">
                        <HomeIcon class="w-4 h-4"/>
                        My Files
                    </Link>
                    <div v-else class="flex items-center">
                        <svg aria-hidden="true" class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                             xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                  d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                  clip-rule="evenodd"></path>
                        </svg>
                        <Link :href="route('myFiles', {folder: ans.path})"
                              class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 dark:text-gray-400 ">
                            {{ ans.name }}
                        </Link>
                    </div>
                </li>
            </ol>

            <div class="flex">
                <label class="flex items-center mr-3">
                    Only Favourites
                    <Checkbox @change="showOnlyFavourites" v-model:checked="onlyFavourites" class="ml-2"/>
                </label>
                <ShareFilesButton :all-selected="allSelected" :selected-ids="selectedIds" />
                <DownloadFilesButton :all="allSelected" :ids="selectedIds" class="mr-2"/>
                <DeleteFilesButton :delete-all="allSelected" :delete-ids="selectedIds" @delete="onDelete" /> <!--passa i props con i valori assegnati al componente-->
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

                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Name
                        </th>
                        <th v-if="search" class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Path
                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Owner
                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Last Modified
                        </th>
                        <th class="text-sm font-medium text-gray-900 px-6 py-4 text-left">
                            Size
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="file of allFiles.data" :key="file.id"
                        @click="toggleFileSelect(file, allFiles.data)"
                        @dblclick="openFolder(file)"
                        class="bg-white border-b transition duration-300 ease-in-out
                                hover:bg-blue-100 cursor-pointer"
                        :class="(selected[file.id] || allSelected) ? 'bg-blue-50' : 'bg-white'">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 w-[30px] max-w-[30px] pr-0">
                             <Checkbox @change="onSelectCheckboxChange(file)" v-model="selected[file.id]"
                             :checked="selected[file.id] || allSelected" /> <!--se selected è vuoto, usiamo allSelected-->
                        </td>
                        <td class="px-6 py-4 max-w-[40px] text-sm font-medium text-yellow-500">
                            <div @click.stop.prevent="addRemoveFavourite(file)">
                                <!--star vuota-->
                                <svg v-if="!file.is_favourite" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0
                                        .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0
                                        0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0
                                        0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0
                                        0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                                <!--star piena-->
                                <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077
                                    2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117
                                    3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373
                                    21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434
                                    2.082-5.005Z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 flex items-center">
                            <FileIcon :file="file" />
                            {{ file.id }}
                            {{ file.name }}
                        </td>
                        <td v-if="search" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 ">
                            {{ file.path }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ file.owner }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ file.updated_at }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ file.size }}
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
import {HomeIcon} from '@heroicons/vue/20/solid'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3'
import FileIcon from '@/Components/app/FileIcon.vue';
import { computed, onMounted, onUpdated, ref } from 'vue';
import { httpGet, httpPost } from '@/Helper/http-helper';
import Checkbox from '@/Components/Checkbox.vue';
import DeleteFilesButton from '@/Components/app/DeleteFilesButton.vue';
import DownloadFilesButton from '@/Components/app/DownloadFilesButton.vue';
import { emitter, ON_SEARCH, showSuccessNotification } from '@/event-bus';
import ShareFilesButton from '@/Components/app/ShareFilesButton.vue';

//Uses
const page = usePage()


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

const onlyFavourites = ref(false)

const loadMoreIntersect = ref(null) //elemento dom che osserviamo con IntersectionObserver

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next
})

let params = null

let search = ref('')

//Methods
function openFolder(file)
{ // funzione che apre una cartella quando viene cliccata due volte (@dbclick="openFolder(file)" nella riga della tabella)
    if(!file.is_folder){
        return;
    }
    router.visit(route('myFiles', {folder: file.path}))
}

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

            // qundo carico nuovi file, se allSelected è true, imposto tutti i loro id a true in selected
            // altrimenti se scrollo risultano sleezioanti ma i loro id non sono in selected, quindi
            // se ne deseleziono uno parte il metodo onSelectCheckboxChange() ma quei file
            // non hanno id in selected e quindi vengono deselezioanti tutti automaticamente
            if (allSelected.value) {
                for(let f of res.data) {
                    selected.value[f.id] = true
                }
            }
        })
}

function onSelectAllChange(){
    // iterando su tutti i file che abbiamo, impostiamo il valore del loro selected uguale a quello di allSelected
    allFiles.value.data.forEach(f => {
        selected.value[f.id] = allSelected.value
    })
}

function toggleFileSelect(file, files) {
    if(event.shiftKey) { // se sto premendo shift, devo selezionare tutti i file compresi allora
        let trueValues = []
        for(var key in selected.value) {
            if (selected.value[key]){
                trueValues.push(key)
            }
        }
        const firstId = trueValues[0]
        let lastId = trueValues[trueValues.length - 1]
        console.log(firstId, lastId, file.id)

        const indexes = Object.keys(files)
        let fileIds = []
        for(var i=indexes[0];  i<indexes[indexes.length-1]; i++) {
            fileIds.push(files[i].id)
        }

        // per tutti gli id che sono compresi, io li seleziono
        if (file.id > lastId) {
            for(var i=lastId; i<=file.id; i++) {
                if (Object.values(fileIds).includes(i)) {
                    selected.value[i] = true
                }
            }
        } else if (file.id < firstId) {
            for(var i=file.id; i<=firstId; i++) {
                if (Object.values(fileIds).includes(i)) {
                    selected.value[i] = true
                }
            }
        } else {
            if ((file.id-firstId) > (lastId-file.id)) {
                console.log('tst1')
                for(var i=firstId; i<=file.id; i++) {
                    if (Object.values(fileIds).includes(i)) {
                        selected.value[i] = true
                    }
                }
            } else {
                console.log('tst2')
                for(var i=file.id; i<=lastId; i++) {
                    if (Object.values(fileIds).includes(i)) {
                        selected.value[i] = true
                    }
                }
            }
        }
    } else { //altrimenti, se click senza shift
        // cambiamo lo stato attuale della checkbox del file (collegato a @click sulla riga del file)
        selected.value[file.id] = !selected.value[file.id]
    }
    // controlliamo anche al click sulla riga se sia opportuno depuntare o spuntare la checkbox di allSelected
    onSelectCheckboxChange(file)
}

function onSelectCheckboxChange(file){
    if (!selected.value[file.id]) { // se il file corrente non è selezionato, allora sicuramente allSelected deve essere false
        allSelected.value = false
    } else { //altrimenti:
        let checked = true //impostiamo una flag di controllo a true

        for (let file of allFiles.value.data) { // per ogni file di allFiles.value.data
            if (!selected.value[file.id]) {      // se non è selezionato mettiamo la flag a false e breakiamo il ciclo
                checked = false
                break
            }
        }

        allSelected.value = checked    // dopo tutti i controlli assegnamo a allSelected il valore della flag di controllo
    }
}

function onDelete() {
    allSelected.value = false
    selected.value = {}
}

function addRemoveFavourite(file) {

    httpPost(route("file.addToFavourites"), {id: file.id})
        .then(() => {
            file.is_favourite = !file.is_favourite
            showSuccessNotification('Selected files have been added to favourites')
        })
        .catch(async (er) => {
            console.log(er.error.message)
        })
}

function showOnlyFavourites()
{
    if (onlyFavourites.value) {
        params.set('favourites', 1)
    } else {
        params.delete('favourites')
    }
    router.get(window.location.pathname+'?'+params.toString())
}


//Hooks
onUpdated( () => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next
    }
})

onMounted(() => {

    params = new URLSearchParams(window.location.search)
    onlyFavourites.value = params.get('favourites') === '1'

    search.value = params.get('search') // on mounted prendo il search
    emitter.on(ON_SEARCH, (value) => { // ad ogni ricerca si aggiorna search
        search.value = value
    })

    //ogni volta che il tergetElement cambia l asua visibilità si chiama l'azione di callback
    // const observer = new IntersectionObserver(callback, options)
    const observer = new IntersectionObserver( (entries) => entries.forEach(entry => entry.isIntersecting && loadMore()), {
        rootMargin: '-250px 0px 0px 0px'
    })

    // observer.observe(targetElement)
    observer.observe(loadMoreIntersect.value)
})


</script>

