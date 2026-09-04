<?php

namespace App\Enums;

enum ImportStatus: int
{
    case Created = 0;
    case Pending = 1;
    case Processing = 2;
    case Completed = 3;
    case Failed = 4;

    public function asText(): string
    {
        return match ($this) {
            self::Created => __("Created"),
            self::Pending => __("Pending"),
            self::Processing => __("Processing"),
            self::Completed => __("Completed"),
            self::Failed => __("Failed"),
        };
    }
}
