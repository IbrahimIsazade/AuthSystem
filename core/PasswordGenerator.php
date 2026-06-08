<?php

class PasswordGenerator {

     // Generates a password based on specific character type counts.
    public static function generate(int $lower, int $upper, int $numbers, int $specials): string {
        $charSets = [
            'lower'   => ['count' => $lower, 'chars' => 'abcdefghijklmnopqrstuvwxyz'],
            'upper'   => ['count' => $upper, 'chars' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
            'numbers' => ['count' => $numbers, 'chars' => '0123456789'],
            'specials'=> ['count' => $specials, 'chars' => '!@#$%^&*()-_=+[]{}|;:,.<>?']
        ];

        $passwordArray = [];

        foreach ($charSets as $set) {
            $poolLength = strlen($set['chars']) - 1;
            for ($i = 0; $i < $set['count']; $i++) {
                $passwordArray[] = $set['chars'][random_int(0, $poolLength)];
            }
        }

        // Shuffle securely to ensure random character distribution
        for ($i = count($passwordArray) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $temp = $passwordArray[$i];
            $passwordArray[$i] = $passwordArray[$j];
            $passwordArray[$j] = $temp;
        }

        return implode('', $passwordArray);
    }
}