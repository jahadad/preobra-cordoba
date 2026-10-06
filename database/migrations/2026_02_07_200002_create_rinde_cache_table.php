<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rinde_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcela_id')->unique()->constrained('parcelas')->cascadeOnDelete();
            $table->string('zona_codigo', 30)->comment('Zone code at calculation time');

            // Input params (snapshot)
            $table->decimal('superficie_terreno', 10, 2);
            $table->decimal('fos_aplicado', 5, 4);
            $table->decimal('fot_aplicado', 5, 4)->nullable();
            $table->decimal('altura_max_aplicada', 6, 2)->nullable();
            $table->unsignedTinyInteger('pisos_max_aplicados')->nullable();

            // Calculated results
            $table->decimal('superficie_ocupable_planta', 10, 2)->comment('terreno * fos');
            $table->decimal('superficie_total_construible', 10, 2)->comment('terreno * fot OR planta * pisos');
            $table->unsignedTinyInteger('pisos_efectivos');
            $table->decimal('superficie_vendible', 10, 2)->comment('total_construible * 0.75');
            $table->unsignedSmallInteger('unidades_estimadas')->comment('vendible / 50m2');

            // Calculation mode
            $table->enum('modo_calculo', ['fot', 'pisos'])->comment('fot=zones with FOT, pisos=zones without FOT');

            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rinde_cache');
    }
};
