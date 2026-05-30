<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE browser_policy_rules ALTER COLUMN value TYPE varchar(2048)'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE browser_policy_rules MODIFY value varchar(2048) NOT NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE browser_policy_rules ALTER COLUMN value TYPE varchar(255)'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE browser_policy_rules MODIFY value varchar(255) NOT NULL'),
            default => null,
        };
    }
};
