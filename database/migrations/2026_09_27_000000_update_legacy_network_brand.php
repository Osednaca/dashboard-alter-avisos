<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $setting = DB::table('system_settings')->where('key', 'network.name')->lockForUpdate()->first();
            $value = $setting ? json_decode($setting->value ?? '', true) : null;

            if (! is_array($value) || ($value['data'] ?? null) !== 'Red Signage TV Colombia') {
                return;
            }

            $value['data'] = 'Red Alter Colombia';

            // Lock the row rather than relying on database-specific JSON equality.
            DB::table('system_settings')->where('id', $setting->id)
                ->update(['value' => json_encode($value, JSON_THROW_ON_ERROR)]);
        });
    }

    public function down(): void
    {
        // A data-only brand change cannot distinguish later user edits; preserve them.
    }
};
