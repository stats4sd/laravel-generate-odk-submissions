<?php

namespace Stats4sd\GenerateOdkSubmissions\Facades;

use Illuminate\Support\Facades\Facade;

class GenerateOdkSubmissions extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-generate-odk-submissions';
    }
}
