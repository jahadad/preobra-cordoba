<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcelas', function (Blueprint $table) {
            $table->id();
            $table->string('nomenclatura', 50)->unique()->comment('16-digit catastral code');
            $table->decimal('superficie_tierra', 12, 2)->nullable()->comment('Urban land area in m2');
            $table->decimal('superficie_mejoras', 12, 2)->nullable()->comment('Built improvements in m2');
            $table->decimal('valuacion', 15, 2)->nullable()->comment('Fiscal valuation');
            $table->string('designacion_oficial', 200)->nullable();
            $table->bigInteger('nro_cuenta')->nullable()->index();
            $table->string('departamento', 100)->nullable();
            $table->string('localidad', 100)->nullable();

            // Foreign key to zone (set by spatial join)
            $table->foreignId('zona_ocupacion_id')->nullable()->constrained('zonas_ocupacion')->nullOnDelete();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Geometry column: POLYGON with SRID 4326
        DB::statement('ALTER TABLE parcelas ADD geom POLYGON NOT NULL SRID 4326');
        DB::statement('CREATE SPATIAL INDEX idx_parcelas_geom ON parcelas(geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelas');
    }
};
