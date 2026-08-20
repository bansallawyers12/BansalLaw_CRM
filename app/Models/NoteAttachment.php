<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteAttachment extends Model
{
    protected $fillable = [
        'note_id',
        'client_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'extension',
        'file_size',
    ];

    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    public function isImage(): bool
    {
        $ext = strtolower((string) $this->extension);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return true;
        }

        return str_starts_with(strtolower((string) $this->mime_type), 'image/');
    }
}
