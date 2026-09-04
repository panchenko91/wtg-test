<?php

namespace App\Enums;

enum ImportStatus: int
{
    case Pending = 1;
    case Processing = 2;
    case Completed = 3;
    case Failed = 4;

    public function asText(): string
    {
        return match ($this) {
            self::Pending => __("Pending"),
            self::Processing => __("Processing"),
            self::Completed => __("Completed"),
            self::Failed => __("Failed"),
        };
    }
}
