<?php

use Illuminate\Database\Seeder;

class UpdateVersionNumber extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('settings')->where('setting_id', 1)->update(['cur_version' => 'v3.5']);
        
    }
}
