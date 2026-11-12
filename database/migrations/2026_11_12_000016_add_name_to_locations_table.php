<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VAJNORY_ADDRESS = 'DK Vajnory, Pod lipami 10036/2, 831 07 Vajnory';

    /**
     * Give each location (seat map) its own name, so several maps of the same
     * venue can share one real address instead of encoding the map variant in it.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        $names = [
            '/sedenie/vajnory-2026.svg' => ['DK Vajnory 2026', self::VAJNORY_ADDRESS],
            '/sedenie/vajnory-parket-dole.svg' => ['DK Vajnory 2027', self::VAJNORY_ADDRESS],
            '/sedenie/seatmap.svg' => ['Predvolená mapa sedenia', null],
        ];

        foreach ($names as $svgMap => [$name, $address]) {
            DB::table('locations')->where('svg_map', $svgMap)->update(array_filter([
                'name' => $name,
                'address' => $address,
            ]));
        }

        // Any other location: fall back to its address as the name.
        DB::table('locations')->whereNull('name')->update(['name' => DB::raw('address')]);

        Schema::table('locations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::table('locations')->where('svg_map', '/sedenie/vajnory-parket-dole.svg')
            ->update(['address' => self::VAJNORY_ADDRESS.' (parket pri vchode)']);

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
