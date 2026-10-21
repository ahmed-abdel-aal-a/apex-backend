<?php

if (!function_exists('bcceil')) {
    /**
     * تقريب لأقرب عدد صحيح لأعلى دائماً (لصالح المطبعة).
     */
    function bcceil(string $value): string
    {
        if (strpos($value, '.') !== false) {
            $parts = explode('.', $value);
            if (isset($parts[1]) && bccomp($parts[1], '0') > 0) {
                return bcadd($parts[0], '1', 0);
            }
            return $parts[0];
        }
        return $value;
    }
}
