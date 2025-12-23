<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private static function len(string $s): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($s);
        }
        return strlen($s);
    }

    public static function requiredString(array $input, string $key, int $min = 1, int $max = 255): ?string
    {
        $v = isset($input[$key]) ? trim((string) $input[$key]) : '';
        if ($v === '') {
            return null;
        }
        if (self::len($v) < $min || self::len($v) > $max) {
            return null;
        }
        return $v;
    }

    public static function optionalString(array $input, string $key, int $max = 255): ?string
    {
        if (!isset($input[$key])) {
            return null;
        }
        $v = trim((string) $input[$key]);
        if ($v === '') {
            return null;
        }
        if (self::len($v) > $max) {
            return null;
        }
        return $v;
    }

    public static function requiredInt(array $input, string $key, int $min = 0, ?int $max = null): ?int
    {
        if (!isset($input[$key]) || $input[$key] === '') {
            return null;
        }
        if (filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $v = (int) $input[$key];
        if ($v < $min) {
            return null;
        }
        if ($max !== null && $v > $max) {
            return null;
        }
        return $v;
    }

    public static function optionalInt(array $input, string $key, int $min = 0, ?int $max = null): ?int
    {
        if (!isset($input[$key]) || $input[$key] === '') {
            return null;
        }
        if (filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $v = (int) $input[$key];
        if ($v < $min) {
            return null;
        }
        if ($max !== null && $v > $max) {
            return null;
        }
        return $v;
    }

    public static function requiredDecimal(array $input, string $key, float $min = 0): ?string
    {
        $raw = isset($input[$key]) ? str_replace(',', '.', trim((string) $input[$key])) : '';
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            return null;
        }
        if ((float) $raw < $min) {
            return null;
        }
        return $raw;
    }

    public static function requiredDate(array $input, string $key): ?string
    {
        $raw = isset($input[$key]) ? trim((string) $input[$key]) : '';
        if ($raw === '') {
            return null;
        }
        $normalized = $raw;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $normalized = $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        $d = \DateTime::createFromFormat('Y-m-d', $normalized);
        if (!$d || $d->format('Y-m-d') !== $normalized) {
            return null;
        }
        return $normalized;
    }

    public static function passwordComplex(string $password, int $minLength = 12): bool
    {
        if (strlen($password) < $minLength) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/\d/', $password)) {
            return false;
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }
        return true;
    }
}
