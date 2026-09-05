<?php

namespace App\Exceptions;

class OfferUnavailableException extends \RuntimeException
{
    public static function noUnits()
    {
        return new self(__('The offer has no available units left.'));
    }
}
