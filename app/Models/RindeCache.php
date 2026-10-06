<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RindeCache extends Model
{
    protected $table = 'rinde_cache';

    protected $fillable = [
        'parcela_id',
        'zona_codigo',
        'superficie_terreno',
        'fos_aplicado',
        'fot_aplicado',
        'altura_max_aplicada',
        'pisos_max_aplicados',
        'superficie_ocupable_planta',
        'superficie_total_construible',
        'pisos_efectivos',
        'superficie_vendible',
        'unidades_estimadas',
        'modo_calculo',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'superficie_terreno' => 'decimal:2',
            'fos_aplicado' => 'decimal:4',
            'fot_aplicado' => 'decimal:4',
            'altura_max_aplicada' => 'decimal:2',
            'pisos_max_aplicados' => 'integer',
            'superficie_ocupable_planta' => 'decimal:2',
            'superficie_total_construible' => 'decimal:2',
            'pisos_efectivos' => 'integer',
            'superficie_vendible' => 'decimal:2',
            'unidades_estimadas' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(Parcela::class);
    }
}
