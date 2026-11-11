<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SVG_MAP = '/sedenie/vajnory-parket-dole.svg';

    /**
     * Turn svg_map from an enum into a plain string, so new seat maps only need
     * an SVG in public/sedenie and a locations row, then add the new map.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('svg_map')->change();
        });

        if (! DB::table('locations')->where('svg_map', self::SVG_MAP)->exists()) {
            $now = Carbon::now();

            DB::table('locations')->insert([
                'address' => 'DK Vajnory, Pod lipami 10036/2, 831 07 Vajnory (parket pri vchode)',
                'svg_map' => self::SVG_MAP,
                'places_total' => 144,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $location = DB::table('locations')->where('svg_map', self::SVG_MAP)->first();

        if ($location && ! DB::table('events')->where('location_id', $location->id)->exists()) {
            DB::table('locations')->where('id', $location->id)->delete();
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->enum('svg_map', ['/sedenie/vajnory-2026.svg', '/sedenie/seatmap.svg'])->change();
        });
    }
};
