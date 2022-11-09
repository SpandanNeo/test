<?php

use Illuminate\Database\Seeder;

class MailTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('mail_types')->delete();
        
        \DB::table('mail_types')->insert(array (
            0 => 
            array (
                'mail_type_id' => 1,
                'name' => 'SendGrid',
                'mail_type' => 'SG',
                'active' => 0,
            ),
            1 => 
            array (
                'mail_type_id' => 2,
                'name' => 'SMTP',
                'mail_type' => 'SM',
                'active' => 0,
            ),
        ));
        
        
    }
}