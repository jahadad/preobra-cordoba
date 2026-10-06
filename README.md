# PreObra

A pre-construction feasibility tool for Córdoba, Argentina. Pick a lot on the map and see what the zoning allows you to build there, how many square meters that gives you, and how a mix of units would play out.

**Live:** https://preobra-production.up.railway.app

## What it does

- **Interactive zoning map** of the city of Córdoba, with every parcel and the zoning rules that apply to it (Ordinance 8256).
- **Buildable area calculator** ("rinde"): from the lot and its zone, the maximum buildable area under the city's rules.
- **Unit-mix simulator**: try different combinations of unit types on that buildable area.

## Data

- IDECOR WFS, the province's open geodata service: 558 zoning areas and about 375,000 parcels.
- Geocoding through Nominatim (OpenStreetMap).
- Parcels are imported in the background on first start if the database has fewer than 1,000.

## Stack

Laravel 12 (PHP 8.3), MySQL, MapLibre GL JS, Tailwind CSS. Deployed on Railway from the Dockerfile.

## Running it locally

```bash
cp .env.example .env
composer install && npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```
