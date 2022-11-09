<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG', 'ffmpeg'),
        'threads'  => 12,
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE', 'ffprobe'),
    ],

    'timeout' => 3600,

    'enable_logging' => true,
];
