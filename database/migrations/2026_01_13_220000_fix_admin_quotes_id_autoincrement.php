<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixAdminQuotesIdAutoincrement extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        if (Schema::hasTable('admin_quotes')) {
            // Check if id is already auto increment to avoid errors
            $columns = DB::select("SHOW COLUMNS FROM admin_quotes LIKE 'id'");
            if (!empty($columns)) {
                $col = $columns[0];
                // Check if Extra contains auto_increment
                $isAuto = strpos($col->Extra, 'auto_increment') !== false;
                $isPri = strpos($col->Key, 'PRI') !== false;

                if (!$isAuto || !$isPri) {
                    // We need to modify it.
                    // If it's not a primary key, we add it.
                    // If it's not auto_increment, we add it.
                    
                    // Note: You cannot modify a column to AUTO_INCREMENT if it is not a key.
                    // So we must do both or ensuring it is a key first.
                    
                    // We'll try a raw statement that handles both for MySQL
                    try {
                        DB::statement("ALTER TABLE admin_quotes MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)");
                    } catch (\Exception $e) {
                        // If primary key already exists, try just modifying the column
                        if (strpos($e->getMessage(), 'Multiple primary key') !== false) {
                             DB::statement("ALTER TABLE admin_quotes MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT");
                        } else {
                            throw $e;
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // We generally don't want to remove auto-increment or primary key in down()
        // as it was likely a fix for a broken state.
    }
}
