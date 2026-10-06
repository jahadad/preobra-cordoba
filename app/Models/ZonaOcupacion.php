<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZonaOcupacion extends Model
{
    protected $table = 'zonas_ocupacion';

    protected $fillable = [
        'idecor_fid',
        'perfil_o_zona',
        'zona_nombre',
        'caracter_urbanistico',
        'fos',
        'fot',
        'altura_maxima',
        'pisos_maximos',
        'pisos_maximos_raw',
        'retiro_frente',
        'retiro_lateral',
        'retiro_fondo',
        'otras_disposiciones',
        'ord_original',
        'ord_modificatoria',
        'es_edificable',
        'actividad_industrial',
        'plano_a_45',
    ];

    protected function casts(): array
    {
        return [
            'fos' => 'decimal:2',
            'fot' => 'decimal:2',
            'altura_maxima' => 'decimal:2',
            'pisos_maximos' => 'integer',
            'es_edificable' => 'boolean',
        ];
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class, 'zona_ocupacion_id');
    }

    /**
     * Whether this zone uses FOT-based calculation (vs pisos-based).
     */
    public function usaFot(): bool
    {
        return $this->fot !== null && $this->fot > 0;
    }
}
