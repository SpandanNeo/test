<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MailSettingUpdateColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('mail_settings', 'mail_type_id')) {
            Schema::table('mail_settings', function (Blueprint $table) {
                $table->integer('mail_type_id')->nullable()->default(0)->after('m_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('mail_settings', 'status')) {
            Schema::table('mail_settings', function($table) {
                $table->integer('status');
            });
        }
    }
}
