<?php
return [
    'ffmpeg' => [
        'required' => true,
        'platforms' => [
            'linux-x86_64' => [
                'url' => 'https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz',
                'archive' => 'tar.xz',
                'binary_name' => 'ffmpeg',
            ],
            'linux-arm64' => [
                'url' => 'https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-arm64-static.tar.xz',
                'archive' => 'tar.xz',
                'binary_name' => 'ffmpeg',
            ],
        ],
    ],
];
