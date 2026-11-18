<?php

return [
    // Use the reference documents' Calibri and Bodoni fonts when installed.
    // On other hosts, point this at a directory containing licensed font files.
    'font_directory' => env('REPORT_FONT_DIRECTORY', PHP_OS_FAMILY === 'Windows'
        ? (getenv('WINDIR') ?: 'C:/Windows').'/Fonts'
        : resource_path('fonts')),
];
