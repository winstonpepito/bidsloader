<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FboFeedError extends Model
{
    protected $fillable = [
        'entry_type',
        'error_message',
        'fbo_file_date',
        'compressed_entry',
        'compressed_original_entry',
        'compressed_stack',
    ];

    protected $casts = [
        'fbo_file_date' => 'date',
    ];

    public function setCompressedEntryAttribute($value): void
    {
        $this->attributes['compressed_entry'] = $value ? gzcompress($value) : null;
    }

    public function getDecompressedEntryAttribute(): ?string
    {
        return $this->attributes['compressed_entry']
            ? gzuncompress($this->attributes['compressed_entry'])
            : null;
    }

    public function setCompressedStackAttribute($value): void
    {
        $this->attributes['compressed_stack'] = $value ? gzcompress($value) : null;
    }

    public function getDecompressedStackAttribute(): ?string
    {
        return $this->attributes['compressed_stack']
            ? gzuncompress($this->attributes['compressed_stack'])
            : null;
    }

    public static function deleteByFboDate(string $date): int
    {
        return self::where('fbo_file_date', $date)->delete();
    }
}
