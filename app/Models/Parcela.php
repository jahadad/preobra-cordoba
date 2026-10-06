<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcela extends Model
{
    protected $fillable = [
        'nomenclatura',
        'superficie_tierra',
        'superficie_mejoras',
        'valuacion',
        'designacion_oficial',
        'nro_cuenta',
        'departamento',
        'localidad',
        'zona_ocupacion_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'superficie_tierra' => 'decimal:2',
            'superficie_mejoras' => 'decimal:2',
            'valuacion' => 'decimal:2',
            'nro_cuenta' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaOcupacion::class, 'zona_ocupacion_id');
    }

    public function rinde(): HasOne
    {
        return $this->hasOne(RindeCache::class, 'parcela_id');
    }
}
