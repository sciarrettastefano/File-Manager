<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreFileRequest extends ParentIdBaseRequest
{

    public function prepareForValidation()
    {
        // Prendi l'array $this->relative_paths se esiste, altrimenti un
        // array vuoto, e rimuovi solo i valori null, lasciando tutto il resto.
        $paths = array_filter($this->relative_paths ?? [], fn($f) => $f != null);

        $this->merge([
            'file_paths' => $paths,
            'folder_name' => $this->detectFolderName($paths)
        ]);
    }

    protected function passedValidation()
    {
        $data = $this->validated();

        $this->replace([
            'file_tree' => $this->buildFileTree($this->file_paths, $data['files'])
        ]);
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'files.*' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (!$this->folder_name) { // se non esiste folder_name è un file e quindi applico questa validazione
                        /** @var $value \Illuminate\Http\UploadedFile */
                        $file = File::query()->where('name', $value->getClientOriginalName())
                            ->where('created_by', Auth::id())
                            ->where('parent_id', $this->parent_id)
                            ->whereNull('deleted_at')
                            ->first();

                        if (!blank($file)) {
                            $fail('File "' . $value->getClientOriginalName() . '" already exists.');
                        }
                    }
                }
            ],
            'folder_name' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        /** @var $value \Illuminate\Http\UploadedFile */
                        $file = File::query()->where('name', $value)
                            ->where('created_by', Auth::id())
                            ->where('parent_id', $this->parent_id)
                            ->whereNull('deleted_at')
                            ->exists();

                        if ($file) {
                            $fail('Folder "' . $value . '" already exists.');
                        }
                    }
                }
            ]
        ]);
    }

    public function detectFolderName($paths)
    {
        // se paths non esiste, return
        if (!$paths){
            return null;
        }

        // explode divide la stringa data come secondo parametro attraverso un separatore dato come primo parametro
        $parts = explode("/", $paths[0]);
        // resituiamo il nome della cartella (prima parte del suo paths)
        return $parts[0];
    }

    private function buildFileTree($filePaths, $files) // funzione che costruisce la struttura ad albero dei file e cartelle che carichiamo in un colpo solo
    {
        // docker ha delle limitazioni sul numero di file caricabile in una volta sola (20 di default)
        // quindi se provassimo a caricarne di più avremmo 20 file ma avremmo i paths di tutti quelli che
        // abbiamo provato a caricare => dimensioniamo l'array $filePaths secondo la dimensione dell'array $files
        $filePaths = array_slice($filePaths, 0, count($files));
        $filePaths = array_filter($filePaths, fn($f) => $f != null);

        $tree = [];

        foreach($filePaths as $ind => $filePath){
            $parts = explode('/', $filePath);

            $currentNode = &$tree; // questa sintassi indica che $currentNode è un riferimento di $tree (e non una copia)
            foreach ($parts as $i => $part){
                if (!isset($currentNode[$part])){
                    $currentNode[$part] = [];
                }

                if ($i === count($parts) - 1) {
                    $currentNode[$part] = $files[$ind];
                } else {
                    $currentNode = &$currentNode[$part];
                }
            }
        }

        return $tree;
    }

}
