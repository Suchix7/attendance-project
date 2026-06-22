<?php
/**
 * Nepali (Bikram Sambat) Calendar Conversion Library
 * Converts Gregorian (AD) dates to Nepali/BS dates
 * All output is in English characters (Roman script)
 */

// Nepali month names in English
define('NEPALI_MONTHS', [
    1  => 'Baisakh',
    2  => 'Jestha',
    3  => 'Ashadh',
    4  => 'Shrawan',
    5  => 'Bhadra',
    6  => 'Ashwin',
    7  => 'Kartik',
    8  => 'Mangsir',
    9  => 'Poush',
    10 => 'Magh',
    11 => 'Falgun',
    12 => 'Chaitra'
]);

define('NEPALI_MONTH_ABBR', [
    1  => 'Bai',
    2  => 'Jes',
    3  => 'Ash',
    4  => 'Shr',
    5  => 'Bha',
    6  => 'Asw',
    7  => 'Kar',
    8  => 'Man',
    9  => 'Pou',
    10 => 'Mag',
    11 => 'Fal',
    12 => 'Cha'
]);

// Days in each Nepali month per BS year
// Data covers BS years 2000 to 2090
$nepali_month_data = [
    2000 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2001 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2002 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2003 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2004 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2005 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2006 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2007 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2008 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2009 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2010 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2011 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2012 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2013 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2014 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2015 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2016 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2017 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2018 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2019 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2020 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2021 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2022 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2023 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2024 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2025 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2026 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2027 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2028 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2029 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2030 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2031 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2032 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2033 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2034 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2035 => [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2036 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2037 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2038 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2039 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2040 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2041 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2042 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2043 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2044 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2045 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2046 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2047 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2048 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2049 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2050 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2051 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2052 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2053 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2054 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2055 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2056 => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2057 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2058 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2059 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2060 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2061 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2062 => [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
    2063 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2064 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2065 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2066 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2067 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2068 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2069 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2070 => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2072 => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2073 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2074 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2075 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2078 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2079 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2080 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2081 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2082 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2083 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2084 => [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
    2085 => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
    2086 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2087 => [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
    2088 => [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
    2089 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2090 => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
];

/**
 * Convert Gregorian (AD) date to Nepali (BS) date
 * 
 * @param int $year  Gregorian year
 * @param int $month Gregorian month (1-12)
 * @param int $day   Gregorian day
 * @return array ['year' => int, 'month' => int, 'day' => int] or false on failure
 */
function gregorianToNepali($year, $month, $day)
{
    global $nepali_month_data;

    // Nepali calendar starts from BS 2000 = April 13/14, 1943 AD
    // Reference point: BS 2000/1/1 = AD 1943/4/14
    $ref_nepali_year = 2000;
    $ref_nepali_month = 1;
    $ref_nepali_day = 1;

    // Reference Gregorian date for BS 2000/01/01
    $ref_greg_year = 1943;
    $ref_greg_month = 4;
    $ref_greg_day = 14;

    // Calculate total days from reference Gregorian date to given date
    $ref_jd = gregorian_to_jd($ref_greg_year, $ref_greg_month, $ref_greg_day);
    $given_jd = gregorian_to_jd($year, $month, $day);
    $total_days = $given_jd - $ref_jd;

    if ($total_days < 0) {
        return false; // Date before BS 2000
    }

    // Walk through Nepali calendar
    $np_year  = $ref_nepali_year;
    $np_month = $ref_nepali_month;
    $np_day   = $ref_nepali_day;

    // Walk year by year
    while ($total_days > 0) {
        if (!isset($nepali_month_data[$np_year])) {
            return false; // Out of range
        }

        $days_in_month = $nepali_month_data[$np_year][$np_month - 1];
        $days_left_in_month = $days_in_month - $np_day;

        if ($total_days <= $days_left_in_month) {
            $np_day += $total_days;
            $total_days = 0;
        } else {
            $total_days -= ($days_left_in_month + 1);
            $np_day = 1;
            $np_month++;
            if ($np_month > 12) {
                $np_month = 1;
                $np_year++;
            }
        }
    }

    return [
        'year'  => $np_year,
        'month' => $np_month,
        'day'   => $np_day,
    ];
}

/**
 * Convert Nepali (BS) date to Gregorian (AD) date
 * 
 * @param int $np_year  BS year
 * @param int $np_month BS month (1-12)
 * @param int $np_day   BS day
 * @return array ['year' => int, 'month' => int, 'day' => int] or false on failure
 */
function nepaliToGregorian($np_year, $np_month, $np_day)
{
    global $nepali_month_data;

    if (!isset($nepali_month_data[$np_year])) {
        return false;
    }

    // Reference: BS 2000/01/01 = AD 1943/04/14
    $ref_nepali_year  = 2000;
    $ref_nepali_month = 1;
    $ref_nepali_day   = 1;
    $ref_greg = mktime(0, 0, 0, 4, 14, 1943);

    // Count total days from reference BS date to given BS date
    $total_days = 0;

    // Full years
    for ($y = $ref_nepali_year; $y < $np_year; $y++) {
        if (!isset($nepali_month_data[$y])) {
            return false;
        }
        foreach ($nepali_month_data[$y] as $m_days) {
            $total_days += $m_days;
        }
    }

    // Full months in given year
    for ($m = 1; $m < $np_month; $m++) {
        $total_days += $nepali_month_data[$np_year][$m - 1];
    }

    // Days in current month (subtract 1 because reference day is included)
    $total_days += ($np_day - 1);

    // Add total days to reference Gregorian date
    $greg_timestamp = $ref_greg + ($total_days * 86400);
    $greg_date = getdate($greg_timestamp);

    return [
        'year'  => $greg_date['year'],
        'month' => $greg_date['mon'],
        'day'   => $greg_date['mday'],
    ];
}

/**
 * Get number of days in a Nepali month
 * 
 * @param int $np_year  BS year
 * @param int $np_month BS month (1-12)
 * @return int|false
 */
function nepali_days_in_month($np_year, $np_month)
{
    global $nepali_month_data;
    if (!isset($nepali_month_data[$np_year])) {
        return false;
    }
    return $nepali_month_data[$np_year][$np_month - 1];
}

/**
 * Get the first day of week (0=Sun, 6=Sat) for a Nepali month
 * 
 * @param int $np_year  BS year
 * @param int $np_month BS month (1-12)
 * @return int 0-6 (Sun-Sat)
 */
function nepali_first_day_of_month($np_year, $np_month)
{
    $greg = nepaliToGregorian($np_year, $np_month, 1);
    if (!$greg) return 0;
    $ts = mktime(0, 0, 0, $greg['month'], $greg['day'], $greg['year']);
    return (int)date('w', $ts); // 0=Sunday, 6=Saturday
}

/**
 * Convert a Gregorian date string (Y-m-d) to a formatted Nepali date string
 * 
 * @param string $gregorian_date  "YYYY-MM-DD"
 * @param string $format          'short' => "DD Mon YYYY BS", 'long' => "DD Month YYYY BS", 'numeric' => "YYYY-MM-DD"
 * @return string
 */
function formatNepaliDate($gregorian_date, $format = 'long')
{
    if (empty($gregorian_date)) return '';
    
    $parts = explode(' ', $gregorian_date); // Handle datetime format "YYYY-MM-DD HH:MM:SS"
    $date_part = $parts[0];
    
    $date_arr = explode('-', $date_part);
    if (count($date_arr) < 3) return $gregorian_date;
    
    list($year, $month, $day) = array_map('intval', $date_arr);
    
    $np = gregorianToNepali($year, $month, $day);
    if (!$np) return $gregorian_date;
    
    $months = NEPALI_MONTHS;
    $abbrs  = NEPALI_MONTH_ABBR;
    
    switch ($format) {
        case 'numeric':
            return sprintf('%04d-%02d-%02d', $np['year'], $np['month'], $np['day']);
        case 'short':
            return sprintf('%d %s %d BS', $np['day'], $abbrs[$np['month']], $np['year']);
        case 'long':
        default:
            return sprintf('%d %s %d BS', $np['day'], $months[$np['month']], $np['year']);
    }
}

/**
 * Get today's date in Nepali BS
 * @return array ['year', 'month', 'day']
 */
function todayNepali()
{
    $y = (int)date('Y');
    $m = (int)date('m');
    $d = (int)date('d');
    return gregorianToNepali($y, $m, $d);
}

/**
 * Get a formatted "today" string in Nepali
 */
function todayNepaliFormatted($format = 'long')
{
    return formatNepaliDate(date('Y-m-d'), $format);
}

/**
 * Helper: Julian Day Number from Gregorian date
 */
function gregorian_to_jd($y, $m, $d)
{
    $a = (int)((14 - $m) / 12);
    $y2 = $y + 4800 - $a;
    $m2 = $m + 12 * $a - 3;
    return $d + (int)((153 * $m2 + 2) / 5) + 365 * $y2 + (int)($y2 / 4) - (int)($y2 / 100) + (int)($y2 / 400) - 32045;
}
