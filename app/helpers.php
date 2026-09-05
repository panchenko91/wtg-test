<?php

use Illuminate\Database\Eloquent\Model;

function app_timezone()
{
    return config('app.timezone');
}

function model_id($value)
{
    if ($value instanceof Model) {
        return $value->getKey();
    }

    if (is_int($value)) {
        return $value;
    }

    return null;
}
