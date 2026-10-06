<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas_ocupacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('idecor_fid')->unique()->comment('IDECOR feature ID');
            $table->string('perfil_o_zona', 30)->index()->comment('Zone code: D, G1, VI, etc.');
            $table->string('zona_nombre', 200)->nullable()->comment('Full zone/corridor name');
            $table->string('caracter_urbanistico', 500)->nullable();
            $table->decimal('fos', 5, 2)->nullable()->comment('FOS as decimal (0.70), converted from IDECOR integer (70)');
            $table->decimal('fot', 5, 2)->nullable()->comment('FOT as decimal, NULL for corridor zones');
            $table->decimal('altura_maxima', 6, 2)->nullable()->comment('Max height in meters');
            $table->unsignedTinyInteger('pisos_maximos')->nullable()->comment('Parsed from string "PB + N pisos"');
            $table->string('pisos_maximos_raw', 100)->nullable()->comment('Original string from IDECOR');
            $table->string('retiro_frente', 200)->nullable();
            $table->string('retiro_lateral', 200)->nullable();
            $table->string('retiro_fondo', 200)->nullable();
            $table->string('otras_disposiciones', 500)->nullable();
            $table->string('ord_original', 100)->nullable();
            $table->string('ord_modificatoria', 100)->nullable();
            $table->boolean('es_edificable')->default(true);
            $table->string('actividad_industrial', 100)->nullable();
            $table->string('plano_a_45', 10)->nullable();
            $table->timestamps();
        });

        // Add geometry column with SRID 4326 (WGS84)
        DB::statement('ALTER TABLE zonas_ocupacion ADD geom POLYGON NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX idx_zonas_geom ON zonas_ocupacion(geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas_ocupacion');
    }
};
