#!/bin/bash
set -e

echo "Running migrations..."
php artisan migrate --force

# Import zones if table is empty
ZONA_COUNT=$(php artisan tinker --execute="echo App\Models\ZonaOcupacion::count();" 2>/dev/null || echo "0")
if [ "$ZONA_COUNT" = "0" ]; then
    echo "Importing zonas from IDECOR..."
    php artisan preobra:import-zonas
else
    echo "Zonas already imported ($ZONA_COUNT)"
fi

# Report parcela count (import is triggered manually via: railway run php artisan preobra:import-parcelas --assign-zonas --calcular-rinde)
PARCELA_COUNT=$(php artisan tinker --execute="echo App\Models\Parcela::count();" 2>/dev/null || echo "0")
echo "Parcelas in DB: $PARCELA_COUNT"

echo "Starting server on port ${PORT:-8080}..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
