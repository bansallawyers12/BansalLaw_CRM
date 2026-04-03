<?php namespace App\Helpers;

class Settings
{
    /**
     * Return site/office setting values. settings table removed; date/time formats are fixed.
     */
    static function sitedata($fieldname)
    {
        if ($fieldname == 'date_format') {
            return 'd/m/Y';
        }
        if ($fieldname == 'time_format') {
            return 'g:i A';
        }
        return 'none';
    }
}