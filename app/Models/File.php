<?php

namespace App\Models;

use App\Traits\HasCreatorAndUpdater;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Support\Str;

class File extends Model
{

    use HasFactory, HasCreatorAndUpdater, NodeTrait, SoftDeletes;

    public function user(): BelongsTo
    {   // restituisce utente che ha creato il file
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {   // restituisce cartella padre
        return $this->belongsTo(File::class, 'parent_id');
    }

    public function starred()
    {   // ci returna se il file sia contenuto nella tabella starred_files o meno verificando tramite id
        // bisogna aggiungere una condizione affinchè filtrimo solo i file che sono dell'user corrente
        // sennò potrei avere un risultato falsato poichè in starred_files lo stesso file può essere in più record
        // come preferito da parte di più users
        return $this->hasOne(StarredFile::class, 'file_id', 'id')
            ->where('user_id', Auth::id());
    }

    public function owner(): Attribute
    {   //restituisce chi ha creato un record
        // Accessor
        // quando accediamo ad un'istanza del model File, questo metodo ci definisce chi ne sia il proprietario prima di riceverne i dati
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['created_by'] == Auth::id() ? 'me' : $this->user->name; //utile poi con implementazione shared_files
            }
        );
    }

    public function isOwnedBy($userId): bool
    {   //ci dice se il record in qestione (this) è stato creato da chi è stato autorizzato
        return $this->created_by ==$userId;
    }

    public function isRoot()
    {   // mi dice se un file è root o meno (a seconda se ha genitori o meno)
        return $this->parent_id === null;
    }

    public function get_file_size()
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        // se $this->size è maggiore di 0, calcola la dimensione come si vede sotto, altrimenti sarà 0
        // log($this->size, 1024) ==> calcola il logaritmo della size in base 1024 (multiplo tra i B, KB, ...)
        // floor() ==> arrotonda per difetto ciò che otteniamo sopra
        // otteniamo così l'arrotondamento dell'esponente della potenza di 1024 che si avvicina di più alla size
        $power = $this->size > 0 ? floor(log($this->size, 1024)) : 0;

        // $this->size / pow(1024, $power) ci dà la size divisa per 1024 elevato all'esponenete trovato prima
        // poi specifichiamo 2 cifre deicmali, '.' per separare i decimali, ',' per separare le migliaia
        // poi concateniamo l'unità di misura, ottenuta mediante l'esponente di prima usato come
        //  indice dell'array delle unità di misura $units
        return number_format($this->size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];

        // per via del floor() avremo un arrotondamento per difetto della dimensione tendenzialmente
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function($model){ // creating... prima che un record venga creato, per calcolarne il path
            // se non ha un parent allora è root e non avrà un path
            if (!$model->parent){
                return;
            }
            // Sintassi usata sotto: if...else "abbreviato":
            // condizione ? valore_se_vero : valore_se_falso
            // se il file non è root, allora gli si appende il path del genitore più il nome del file
            // altrimenti, se è root, il suo path diventa solamente il proprio nome file
            $model->path = (!$model->parent->isRoot() ? $model->parent->path . '/' : '') . Str::slug($model->name);
        });

//        static::deleted(function(File $model) { #per eliminare dallo storage i file
//            # se il file non è una cartella (cartella non ha file direttamente collegati nello storage),
//            # allora cancella il file fisico dallo storage
//            if (!$model->is_folder) {
//                Storage::delete($model->storage_path);
//            }
//        });
    }

    public function moveToTrash() {
        $this->deleted_at = Carbon::now();

        return $this->save();
    }

    public function deleteForever() {
        $this->deleteFilesFromStorage([$this]);
        $this->forceDelete();
    }

    public function deleteFilesFromStorage($files) {
        // per ogni file nell'array passato come parametro, se il file è una cartella,
        // ne elimina dallo storage tutti i file contenuti
        // se è un file singolo elimina semplicemente quel file dallo storage
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->deleteFilesFromStorage($file->children);
            } else {
                //Storage::delete($file->storage_path);
                Storage::disk('local')->delete($file->storage_path);
            }
        }
    }

    public static function getSharedWithMe()
    {   // metodo per non ripetere la query degli shared with me
        return File::query()
            ->select('files.*')
            ->join('file_shares', 'file_shares.file_id', 'files.id')
            ->where('file_shares.user_id', Auth::id())
            ->orderBy('file_shares.created_at', 'desc')
            ->orderBy('files.id', 'desc');
    }

    public static function getSharedByMe()
    {   // metodo per non ripetere la query degli shared by me
        return File::query()
            ->select('files.*')
            ->join('file_shares', 'file_shares.file_id', 'files.id')
            ->where('files.created_by', Auth::id())
            ->orderBy('file_shares.created_at', 'desc')
            ->orderBy('files.id', 'desc');
    }

    public function versions()
    {
        return $this->hasMany(FileVersion::class);
    }

    public function latestVersion()
    {
        return $this->hasOne(FileVersion::class)->latestOfMany();
    }

}
