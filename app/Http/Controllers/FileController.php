<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\FilesActionRequest;
use App\Http\Requests\ShareFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Mail\ShareFilesMail;
use App\Models\File;
use App\Models\FileShare;
use App\Models\FileVersion;
use App\Models\StarredFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FileController extends Controller
{
    public function myFiles(Request $request, string $folder = null)
    {
        $search = $request->get('search');
        if ($folder) { // se folder esiste
            $folder = File::query()
                ->where('created_by', Auth::id())
                ->where('path', $folder)
                ->firstOrFail();
        }
        if (!$folder) {
            $folder = $this->getRoot();
        }

        $favourites = (int)$request->get('favourites');

        $query = File::query()
            ->select('files.*')                     // seleziono da files (non da starred_files)
            ->with('starred')                       // per caricare relazione con starred files
            ->where('created_by', Auth::id())       // deve essere stato creato da chi si è autenticatoù
            ->where('_lft', '!=', 1)                // non voglio prendere la root (in nessun caso => metto qua la condizione)
            ->orderBy('is_folder', 'desc')          // ordinamento: prima cartelle
            ->orderBy('files.created_at', 'desc')   // ordinamento: cronologico
            ->orderBy('files.id', 'asc');          // ordinamento: per id

        if ($search) { // se c'è una ricerca non mi interessa in che cartella sono, ma vorrò inserire nella query i filtri della mia ricerca
            $query->where('name', 'like', "%$search%");
        } else { // se non c'è una riceerca allora mi interessa e lo aggiungo alla query
            $query->where('parent_id', $folder->id);       // parent_id è valore effettivamente del parent_id del genitore
        }

        // controllo se la checkbox per vedere solo i preferiti è selezionata
        // se sì, faccio una join sulla query verificando con gli id dei record
        // in starred_files per vedere quali record (da files) siano da mostrare
        // (verificando sempre anche user_id per non prendere record sbagliati)
        if ($favourites === 1) {
            $query->join('starred_files', 'starred_files.file_id', '=', 'files.id')
                ->where('starred_files.user_id', Auth::id());
        }

        $files = $query->paginate(10); // vogliamo mostrare 10 records trovati per pagina

        // in modo che i dati effettivamente restituiti siano nel formato che vogliamo e quelli che vogliamo
        $files = FileResource::collection($files);

        // se la $request richiede json, allora è una di quelle definite da noi con httpGet, e restituiamo
        // i file da caricare nella seconda pagina
        if ($request->wantsJson()) {
            return $files;
        }

        //altrimenti è una pagina "normale" e lasciamo che sia renderizzata da Inertia

        $ancestors = FileResource::collection([...$folder->ancestors, $folder]);

        $folder = new FileResource($folder);

        // return della pagina 'MyFiles' passando anche i dati $files
        return Inertia::render('MyFiles', compact('files', 'folder', 'ancestors'));
    }

    public function trash(Request $request)
    {
        $search = $request->get('search');
        $query = File::onlyTrashed()
            ->where('created_by', Auth::id())
            ->orderBy('is_folder', 'desc')
            ->orderBy('deleted_at', 'desc')
            ->orderBy('files.id', 'desc');

        if ($search) { // filtro dati mostrati nella pagina se search esiste
            $query->where('name', 'like', "%$search%");
        }

        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) { //per permettere caricamento di più pagine di dati
            return $files;
        }

        return Inertia::render('Trash', compact('files'));
    }

    public function sharedWithMe(Request $request)
    {
        $search = $request->get('search');
        $query = File::getSharedWithMe();

        if ($search) { // filtro dati mostrati nella pagina se search esiste
            $query->where('name', 'like', "%$search%");
        }

        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) { // per permettere caricamento di più pagine di dati
            return $files;
        }

        return Inertia::render('SharedWithMe', compact('files'));
    }

    public function sharedByMe(Request $request)
    {
        $search = $request->get('search');
        $query = File::getSharedByMe();

        if ($search) { // filtro dati mostrati nella pagina se search esiste
            $query->where('name', 'like', "%$search%");
        }

        $files = $query->paginate(10);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) { // per permettere caricamento di più pagine di dati
            return $files;
        }

        return Inertia::render('SharedByMe', compact('files'));
    }

    public function store(StoreFileRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;
        $user = $request->user();
        $fileTree = $request->file_tree;

        if (!$parent) { // se non c'è il parent allora siamo nella root
            $parent = $this->getRoot();
        }

        if (!empty($fileTree)) { // se l'albero non è vuoto me lo salvo
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            foreach ($data['files'] as $file) {
                /** @var \Illuminate\Http\UploadedFile $file */

                $this->saveFile($file, $user, $parent);
            }
        }
    }

    public function storeHandlingVersions(StoreFileRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;
        $user = $request->user();
        $fileTree = $request->file_tree;

        if (!$parent) { // se non c'è il parent allora siamo nella root
            $parent = $this->getRoot();
        }

        if (!empty($fileTree)) { // se l'albero non è vuoto me lo salvo
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            foreach ($data['files'] as $file) {
                /** @var \Illuminate\Http\UploadedFile $file */

                // prendo dal db il record con i dati della richiesta
                $existingFile = File::query()->where('name', $file->getClientOriginalName())
                    ->where('created_by', Auth::id())
                    ->where('parent_id', $parent->id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingFile) {
                    $id = $existingFile->id;
                } else {
                    $id = null;
                }

                $existingVersion = FileVersion::query()->where('file_id', $id)
                    ->orderBy('version_number', 'desc')
                    ->first();

                if ($existingVersion) { // se il record esiste
                    // calcolo gli hash del record trovato e del file della richiesta
                    $fileHash = hash_file('sha256', $file->getRealPath());
                    $existingHash = $existingVersion->hash;

                    // confronto gli hash
                    if ($fileHash === $existingHash) { // se sono uguali skippo il ciclo all'else
                        throw ValidationException::withMessages([
                            'files' => 'File "' . $file->getClientOriginalName() . '" already exists.'
                        ]);
                    } else { // se sono diversi allora devo creare una nuova versione del file
                        $version_number = FileVersion::query()->where('file_id', $id)->max('version_number') + 1;
                        $this->saveVersion($file, $id, $user, $version_number, $fileHash);
                    }

                } else { // altrimenti salvo il file e la sua prima versione
                    $hash = hash_file('sha256', $file->getRealPath());
                    $this->saveFile($file, $user, $parent);
                    $file_id = File::query()->where('name', $file->getClientOriginalName())->first()->id;
                    $this->saveVersion($file, $file_id, $user, 1, $hash);
                }
            }
        }
    }

    public function createFolder(StoreFolderRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        if (!$parent) {
            $parent = $this->getRoot(); //se il parent non è dato, allora prendiamo la root
        }

        $file = new File();
        $file->is_folder = 1;
        $file->name = $data['name'];

        $parent->appendNode($file); //metodo di nestedSet che annida $file al suo $parent
    }

    private function getRoot()
    { //restituisce la root
        return File::query()->whereIsRoot()->where('created_by', Auth::id())->firstOrFail();
    }

    public function saveFileTree($fileTree, $parent, $user)
    {
        foreach ($fileTree as $name => $file) {
            if (is_array($file)) {
                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $name;

                $parent->appendNode($folder);
                $this->saveFileTree($file, $folder, $user);
            } else {
                $this->saveFile($file, $user, $parent);
            }
        }
    }

    public function destroy(FilesActionRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        // se esiste $data['all'] sono stati selezionati tutti i file e quindi elimino ogni figlio del parent
        if ($data['all']) {
            $children = $parent->children;

            foreach ($children as $child) {
                //$child->delete(); // avendo SoftDeletes, ci aggiunge il campo 'deleted_at', non elimina completamente da db
                $child->moveToTrash();
            }
        } else { // altrimenti accediamo agli id, se $data['ids'] esiste ed è non null, allora usalo. → Altrimenti usa un array vuoto [].
            foreach ($data['ids'] ?? [] as $id) {
                $file = File::find($id);
                if ($file) {
                    $file->moveToTrash();
                }
            }
        }

        // fa tornare alla pagina della cartella genitore del file eliminato
        return to_route('myFiles', ['folder' => $parent->path]);
    }

    public function download(FilesActionRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        // se $all è false e $ids è array vuoto (nulla di selezionato), restituisci il messaggio sotto semplicemente
        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        // se sono tutti selezionati creo una zip con tutti i file
        if ($all) {
            $url = $this->createZip($parent->children);
            $filename = $parent->name . '.zip';
        } else { // se non $all
            [$url, $filename] = $this->getDownloadUrl($ids, $parent->name);
        }

        // infine restituisco url e filename
        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    private function saveFile($file, $user, $parent): void
    {
        $path = $file->store('/files' . $user->id);

        $model = new File();
        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();

        $parent->appendNode($model);
    }

    private function saveVersion($file, $file_id, $user, $version_number, $hash)
    {
        $path = $file->store('/files' . $user->id);

        $model = new FileVersion();
        $model->file_id = $file_id;
        $model->version_number = $version_number;
        $model->hash = $hash;
        $model->storage_path = $path;
        $model->save();
    }

    public function createZip($files): string
    {
        $zipPath = 'zip/' . Str::random() . '.zip'; // crea una stringa casuale come path della zip

        // controlla se esite la directory in cui salvare la zip,
        // se non esiste la crea direttamente dal filesystem di Laravel
        if (!Storage::disk('public')->exists('zip')) {
            Storage::disk('public')->makeDirectory('zip');
        }

        $zipFile = Storage::disk('public')->path($zipPath); //ottiene path assoluto nel filesystem del file da creare

        $zip = new \ZipArchive();

        // apre la zip creandola o sovrascrivendola a seconda che esiste già o meno
        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $this->addFilesToZip($zip, $files);
        }

        $zip->close(); // chiude e finalizza la zip aperta precedentemente

        return asset(Storage::url($zipPath)); // restituisce url pubblico del fiel zip (asset() lo trasforma in un url completo)
    }

    private function addFilesToZip($zip, $files, $ancestors = '')
    {   // cicla su tutti i file in input
        foreach ($files as $file) {
            if ($file->is_folder) { // se il file è una cartella chiama una ricorsione con i suoi figli passati come file
                // ed il suo nome come ancestor (che verrà aggiunto al percorso)
                $this->addFilesToZip($zip, $file->children, $ancestors . $file->name . '/');
            } else { // se è file lo aggiunge alla zip con nome corretto relativo alla struttra (con acnestor)
                $zip->addFile(Storage::path($file->storage_path), $ancestors . $file->name);
            }
        }
    }

    public function restore(TrashFilesRequest $request)
    {
        $data = $request->validated(); // prendiamo i dati validati della richiesta

        // se sono tutti selezionati restoriamo tutti i file nel trash
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->restore();
            }
        } else { // altrimenti restoriamo filtrando tramite id selezionati solo
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->restore();
            }
        }

        // ritorna alla pagina trash aggiornata
        return to_route('trash');
    }

    public function deleteForever(TrashFilesRequest $request)
    {
        $data = $request->validated(); // prendiamo i dati validati della richiesta

        // se sono tutti selezionati eliminiamo definitivamente tutti i file nel trash
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        } else { // altrimenti eliminiamo definitivamente filtrando tramite id selezionati solo
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        }

        // ritorna alla pagina trash aggiornata
        return to_route('trash');
    }

    public function addToFavourites(AddToFavouritesRequest $request)
    {
        $data = $request->validated();

        $id = $data['id'];
        $file = File::find($id);
        $user_id = Auth::id();

        // cerco se lo starredFile esista
        $starredFile = StarredFile::query()
            ->where('file_id', $file->id)
            ->where('user_id', $user_id)
            ->first();

        if ($starredFile) { // se lo starredFile esiste lo rimuovo dai preferiti
            $starredFile->delete();
        } else { // se non esiste lo aggiungo

            StarredFile::create([
                'file_id' => $file->id,
                'user_id' => $user_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        //non serve un vero redirect o una vera risposta, quindi diamo la redirect alla pagina stessa semplicemente
        return redirect()->back();
    }

    public function share(ShareFilesRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $email = $data['email'] ?? false;
        $ids = $data['ids'] ?? [];

        // se $all è false e $ids è array vuoto (nulla di selezionato), restituisci il messaggio sotto semplicemente
        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to share'
            ];
        }

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return redirect()->back();
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::find($ids);
        }


        // qui sotto controllo se esistono record uguale a quello che sto per creare ed in caso scarto irecord già presenti
        // (NB: ricorda che stessi file ma user diversi sono record diversi)
        $data = [];
        $ids = Arr::pluck($files, 'id');
        $existingFileIds = FileShare::query()
            ->whereIn('file_id', $ids)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('file_id');

        foreach($files as $file) {
            if ($existingFileIds->has($file->id)) {
                continue;
            }
            $data[] = [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        FileShare::insert($data);

        $url = '/file/download-shared-by-me';

        Mail::to($user)->send(new ShareFilesMail($user, Auth::user(), $files));

        return redirect()->back();
    }

    public function downloadSharedWithMe(FilesActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        // se $all è false e $ids è array vuoto (nulla di selezionato), restituisci il messaggio sotto semplicemente
        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        $zipName = 'shared_with_me';
        // se sono tutti selezionati creo una zip con tutti i file
        if ($all) {
            $files = File::getSharedWithMe()->get(); // prendo i file condivisi con me
            $url = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else { // se non $all
            [$url, $filename] = $this->getDownloadUrl($ids, $zipName);
        }

        // infine restituisco url e filename
        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    public function downloadSharedByMe(FilesActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        // se $all è false e $ids è array vuoto (nulla di selezionato), restituisci il messaggio sotto semplicemente
        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }

        $zipName = 'shared_by_me';
        // se sono tutti selezionati creo una zip con tutti i file
        if ($all) {
            $files = File::getSharedByMe()->get(); // prendo i file condivisi con me
            $url = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else { // se non $all
            [$url, $filename] = $this->getDownloadUrl($ids, $zipName);
        }

        // infine restituisco url e filename
        return [
            'url' => $url,
            'filename' => $filename
        ];
    }

    private function getDownloadUrl(array $ids, $zipName)
    { // per evitare ripetizioni di codice della logica del download

        // il controllo sui file e la loro relazione con l'utente corrente è fatto direttamente nella richiesta
        if (count($ids) === 1) { // se l'id selezionato è uno solo
            $file = File::find($ids[0]); //prendo il primo id
            if ($file->is_folder) { // se è una cartella
                if ($file->children->count() === 0) { // controllo se ha file al suo interno
                    return [
                        'message' => 'The folder is empty'
                    ];
                } // e se non è vuota creo la zip
                $url = $this->createZip($file->children);
                $filename = $file->name . '.zip';
            } else { // se non è una cartella
                $filename = pathinfo($file->storage_path, PATHINFO_BASENAME); // definisco la destinazione, che sarà nella cartella public del progetto

                $dest = Storage::disk('public')->putFileAs('', new HttpFile(Storage::disk('local')->path($file->storage_path)), $filename);

                $url = asset(Storage::url($dest)); // gli dò url e filename
                $filename = $file->name;
            }
        } else { // se gli id selezioanti sono più di uno
            $files = File::query()->whereIn('id', $ids)->get();  // prendo tutti gli id selezionati
            $url = $this->createZip($files); // ne creo la zip

            $filename = $zipName . '.zip';
        }

        return [$url, $filename];
    }

}
