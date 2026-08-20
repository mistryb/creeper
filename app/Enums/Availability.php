<?php

namespace App\Enums;

enum Availability: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Preorder = 'preorder';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::OutOfStock => 'Out of stock',
            self::Preorder => 'Pre-order',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * Map the many ways an agent might describe availability onto the enum.
     */
    public static function parse(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? self::InStock : self::OutOfStock;
        }

        if (! is_string($value)) {
            return self::Unknown;
        }

        $normalized = str_replace([' ', '-'], '_', mb_strtolower(trim($value)));

        return match ($normalized) {
            'in_stock', 'instock', 'available', 'yes', 'true', 'in_stock_online' => self::InStock,
            'out_of_stock', 'outofstock', 'unavailable', 'sold_out', 'soldout', 'no', 'false' => self::OutOfStock,
            'preorder', 'pre_order', 'backorder', 'back_order' => self::Preorder,
            default => self::Unknown,
        };
    }
}
