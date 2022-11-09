<?php

namespace App\Imports;

use App\Sound;
use Maatwebsite\Excel\Concerns\ToModel;

class SoundsImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
       
        return new Sound([
            'sound_id'     => $row[0],
            'title'     => $row[1],
            // 'album'    => $row[5], 
            // 'artist' => $row[6],
        ]);
    }
}
