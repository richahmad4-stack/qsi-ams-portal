<?php

namespace App\Services;

class PasswordPolicy
{
    public const MESSAGE = 'Use at least 12 characters with uppercase, lowercase, number, and symbol.';

    public function isStrong(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
    }

    public function temporaryPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $symbols = '!@#$%^&*';
        $password = [
            $alphabet[random_int(0, 23)],
            $alphabet[random_int(24, 47)],
            (string) random_int(2, 9),
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = count($password); $i < 16; $i++) {
            $pool = $alphabet . $symbols . '23456789';
            $password[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }
}
