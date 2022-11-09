<?php

use Illuminate\Database\Seeder;

class CategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('categories')->delete();
        
        \DB::table('categories')->insert(array (
            0 => 
            array (
                'cat_id' => 2,
                'cat_name' => 'Bollywood Songs',
                'rank' => 1,
                'added_on' => '2020-12-09 12:45:51',
                'parent_id' => NULL,
                'deleted' => 0,
            ),
            1 => 
            array (
                'cat_id' => 4,
                'cat_name' => 'English',
                'rank' => 3,
                'added_on' => '2020-12-09 12:46:14',
                'parent_id' => NULL,
                'deleted' => 0,
            ),
            2 => 
            array (
                'cat_id' => 5,
                'cat_name' => 'Dialogs',
                'rank' => 4,
                'added_on' => '2020-12-09 12:46:22',
                'parent_id' => NULL,
                'deleted' => 0,
            ),
            3 => 
            array (
                'cat_id' => 6,
                'cat_name' => 'Hindi Songs',
                'rank' => 5,
                'added_on' => '2020-12-09 12:46:56',
                'parent_id' => NULL,
                'deleted' => 0,
            ),
        ));
        
        
    }
}