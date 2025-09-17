<?php

namespace Durableprogramming\LaravelTypst\Facades;

use Illuminate\Support\Facades\Facade;

class Typst extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'typst';
    }
}
