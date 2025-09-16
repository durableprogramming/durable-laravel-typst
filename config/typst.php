<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Typst Binary Path
    |--------------------------------------------------------------------------
    |
    | Path to the Typst binary. If Typst is installed globally, you can use
    | 'typst'. Otherwise, provide the full path to the binary.
    |
    */
    'bin_path' => env('TYPST_BIN_PATH', 'typst'),

    /*
    |--------------------------------------------------------------------------
    | Working Directory
    |--------------------------------------------------------------------------
    |
    | Directory where temporary Typst files will be created and compiled.
    | This should be a writable directory.
    |
    */
    'working_directory' => env('TYPST_WORKING_DIR', storage_path('typst')),

    /*
    |--------------------------------------------------------------------------
    | Compilation Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for Typst compilation to complete.
    |
    */
    'timeout' => env('TYPST_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Default Output Format
    |--------------------------------------------------------------------------
    |
    | Default output format for compiled documents. Supported formats include
    | 'pdf', 'png', 'svg', etc.
    |
    */
    'format' => env('TYPST_FORMAT', 'pdf'),

    /*
    |--------------------------------------------------------------------------
    | Font Paths
    |--------------------------------------------------------------------------
    |
    | Additional font directories that Typst should search for fonts.
    | These paths will be added to Typst's font search path.
    |
    */
    'font_paths' => [
        // Add custom font directories here
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Root Directory
    |--------------------------------------------------------------------------
    |
    | Default root directory for Typst compilation. This can be overridden
    | per compilation request.
    |
    */
    'root' => env('TYPST_ROOT', null),
];