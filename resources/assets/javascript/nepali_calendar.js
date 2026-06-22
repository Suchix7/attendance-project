/**
 * Nepali (Bikram Sambat) Calendar Conversion Library - JavaScript
 * Converts Gregorian (AD) dates to Nepali/BS dates
 * All output in English (Roman script)
 */

const NepaliCalendar = (function () {

    const NEPALI_MONTHS = [
        '', 'Baisakh', 'Jestha', 'Ashadh', 'Shrawan', 'Bhadra', 'Ashwin',
        'Kartik', 'Mangsir', 'Poush', 'Magh', 'Falgun', 'Chaitra'
    ];

    const NEPALI_MONTH_ABBR = [
        '', 'Bai', 'Jes', 'Ash', 'Shr', 'Bha', 'Asw',
        'Kar', 'Man', 'Pou', 'Mag', 'Fal', 'Cha'
    ];

    // Days in each month per BS year (index 0 = month 1/Baisakh)
    const monthData = {
        2000: [30,32,31,32,31,30,30,30,29,30,29,31],
        2001: [31,31,32,31,31,31,30,29,30,29,30,30],
        2002: [31,31,32,32,31,30,30,29,30,29,30,30],
        2003: [31,32,31,32,31,30,30,30,29,29,30,31],
        2004: [30,32,31,32,31,30,30,30,29,30,29,31],
        2005: [31,31,32,31,31,31,30,29,30,29,30,30],
        2006: [31,31,32,32,31,30,30,29,30,29,30,30],
        2007: [31,32,31,32,31,30,30,30,29,29,30,31],
        2008: [31,31,31,32,31,31,29,30,30,29,29,31],
        2009: [31,31,32,31,31,31,30,29,30,29,30,30],
        2010: [31,31,32,32,31,30,30,29,30,29,30,30],
        2011: [31,32,31,32,31,30,30,30,29,29,30,31],
        2012: [31,31,31,32,31,31,29,30,30,29,30,30],
        2013: [31,31,32,31,31,31,30,29,30,29,30,30],
        2014: [31,31,32,32,31,30,30,29,30,29,30,30],
        2015: [31,32,31,32,31,30,30,30,29,29,30,31],
        2016: [31,31,31,32,31,31,29,30,30,29,30,30],
        2017: [31,31,32,31,31,31,30,29,30,29,30,30],
        2018: [31,32,31,32,31,30,30,29,30,29,30,30],
        2019: [31,32,31,32,31,30,30,30,29,30,29,31],
        2020: [31,31,31,32,31,31,30,29,30,29,30,30],
        2021: [31,31,32,31,31,31,30,29,30,29,30,30],
        2022: [31,32,31,32,31,30,30,30,29,29,30,30],
        2023: [31,32,31,32,31,30,30,30,29,30,29,31],
        2024: [31,31,31,32,31,31,30,29,30,29,30,30],
        2025: [31,31,32,31,31,31,30,29,30,29,30,30],
        2026: [31,32,31,32,31,30,30,30,29,29,30,31],
        2027: [30,32,31,32,31,30,30,30,29,30,29,31],
        2028: [31,31,32,31,31,31,30,29,30,29,30,30],
        2029: [31,31,32,31,32,30,30,29,30,29,30,30],
        2030: [31,32,31,32,31,30,30,30,29,29,30,31],
        2031: [30,32,31,32,31,30,30,30,29,30,29,31],
        2032: [31,31,32,31,31,31,30,29,30,29,30,30],
        2033: [31,31,32,32,31,30,30,29,30,29,30,30],
        2034: [31,32,31,32,31,30,30,30,29,29,30,31],
        2035: [30,32,31,32,31,31,29,30,30,29,29,31],
        2036: [31,31,32,31,31,31,30,29,30,29,30,30],
        2037: [31,31,32,32,31,30,30,29,30,29,30,30],
        2038: [31,32,31,32,31,30,30,30,29,29,30,31],
        2039: [31,31,31,32,31,31,29,30,30,29,30,30],
        2040: [31,31,32,31,31,31,30,29,30,29,30,30],
        2041: [31,31,32,32,31,30,30,29,30,29,30,30],
        2042: [31,32,31,32,31,30,30,30,29,29,30,31],
        2043: [31,31,31,32,31,31,29,30,30,29,30,30],
        2044: [31,31,32,31,31,31,30,29,30,29,30,30],
        2045: [31,32,31,32,31,30,30,29,30,29,30,30],
        2046: [31,32,31,32,31,30,30,30,29,29,30,31],
        2047: [31,31,31,32,31,31,30,29,30,29,30,30],
        2048: [31,31,32,31,31,31,30,29,30,29,30,30],
        2049: [31,32,31,32,31,30,30,30,29,29,30,30],
        2050: [31,32,31,32,31,30,30,30,29,30,29,31],
        2051: [31,31,31,32,31,31,30,29,30,29,30,30],
        2052: [31,31,32,31,31,31,30,29,30,29,30,30],
        2053: [31,32,31,32,31,30,30,30,29,29,30,30],
        2054: [31,32,31,32,31,30,30,30,29,30,29,31],
        2055: [31,31,32,31,31,31,30,29,30,29,30,30],
        2056: [31,31,32,31,32,30,30,29,30,29,30,30],
        2057: [31,32,31,32,31,30,30,30,29,29,30,31],
        2058: [30,32,31,32,31,30,30,30,29,30,29,31],
        2059: [31,31,32,31,31,31,30,29,30,29,30,30],
        2060: [31,31,32,32,31,30,30,29,30,29,30,30],
        2061: [31,32,31,32,31,30,30,30,29,29,30,31],
        2062: [30,32,31,32,31,31,29,30,29,30,29,31],
        2063: [31,31,32,31,31,31,30,29,30,29,30,30],
        2064: [31,31,32,32,31,30,30,29,30,29,30,30],
        2065: [31,32,31,32,31,30,30,30,29,29,30,31],
        2066: [31,31,31,32,31,31,29,30,30,29,29,31],
        2067: [31,31,32,31,31,31,30,29,30,29,30,30],
        2068: [31,31,32,32,31,30,30,29,30,29,30,30],
        2069: [31,32,31,32,31,30,30,30,29,29,30,31],
        2070: [31,31,31,32,31,31,29,30,30,29,30,30],
        2071: [31,31,32,31,31,31,30,29,30,29,30,30],
        2072: [31,32,31,32,31,30,30,29,30,29,30,30],
        2073: [31,32,31,32,31,30,30,30,29,29,30,31],
        2074: [31,31,31,32,31,31,30,29,30,29,30,30],
        2075: [31,31,32,31,31,31,30,29,30,29,30,30],
        2076: [31,32,31,32,31,30,30,30,29,29,30,30],
        2077: [31,32,31,32,31,30,30,30,29,30,29,31],
        2078: [31,31,31,32,31,31,30,29,30,29,30,30],
        2079: [31,31,32,31,31,31,30,29,30,29,30,30],
        2080: [31,32,31,32,31,30,30,30,29,29,30,30],
        2081: [31,32,31,32,31,30,30,30,29,30,29,31],
        2082: [31,31,32,31,31,31,30,29,30,29,30,30],
        2083: [31,31,32,31,31,31,30,29,30,29,30,30],
        2084: [31,31,32,31,31,30,30,30,29,30,30,30],
        2085: [31,32,31,32,30,31,30,30,29,30,30,30],
        2086: [30,32,31,32,31,30,30,30,29,30,30,30],
        2087: [31,31,32,31,31,31,30,30,29,30,30,30],
        2088: [30,31,32,32,30,31,30,30,29,30,30,30],
        2089: [30,32,31,32,31,30,30,30,29,30,30,30],
        2090: [30,32,31,32,31,30,30,30,29,30,30,30],
    };

    // Reference: BS 2000/01/01 = AD 1943/04/14
    const REF_BS_YEAR = 2000;
    const REF_BS_MONTH = 1;
    const REF_BS_DAY = 1;
    const REF_GREG_DATE = new Date(1943, 3, 14); // months are 0-indexed in JS

    function daysBetween(d1, d2) {
        const msPerDay = 24 * 60 * 60 * 1000;
        const utc1 = Date.UTC(d1.getFullYear(), d1.getMonth(), d1.getDate());
        const utc2 = Date.UTC(d2.getFullYear(), d2.getMonth(), d2.getDate());
        return Math.floor((utc2 - utc1) / msPerDay);
    }

    /**
     * Convert Gregorian date to Nepali BS
     * @param {Date|string} dateInput - JS Date object or "YYYY-MM-DD" string
     * @returns {object} { year, month, day } in BS, or null
     */
    function gregorianToNepali(dateInput) {
        let gDate;
        if (typeof dateInput === 'string') {
            const parts = dateInput.split('-');
            gDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        } else {
            gDate = dateInput;
        }

        let totalDays = daysBetween(REF_GREG_DATE, gDate);
        if (totalDays < 0) return null;

        let npYear = REF_BS_YEAR;
        let npMonth = REF_BS_MONTH;
        let npDay = REF_BS_DAY;

        while (totalDays > 0) {
            if (!monthData[npYear]) return null;
            const daysInMonth = monthData[npYear][npMonth - 1];
            const daysLeftInMonth = daysInMonth - npDay;

            if (totalDays <= daysLeftInMonth) {
                npDay += totalDays;
                totalDays = 0;
            } else {
                totalDays -= (daysLeftInMonth + 1);
                npDay = 1;
                npMonth++;
                if (npMonth > 12) {
                    npMonth = 1;
                    npYear++;
                }
            }
        }

        return { year: npYear, month: npMonth, day: npDay };
    }

    /**
     * Convert Nepali BS to Gregorian Date
     * @param {number} npYear
     * @param {number} npMonth
     * @param {number} npDay
     * @returns {Date|null}
     */
    function nepaliToGregorian(npYear, npMonth, npDay) {
        if (!monthData[npYear]) return null;

        let totalDays = 0;
        for (let y = REF_BS_YEAR; y < npYear; y++) {
            if (!monthData[y]) return null;
            for (let m = 0; m < 12; m++) {
                totalDays += monthData[y][m];
            }
        }
        for (let m = 1; m < npMonth; m++) {
            totalDays += monthData[npYear][m - 1];
        }
        totalDays += (npDay - 1);

        const result = new Date(REF_GREG_DATE);
        result.setDate(result.getDate() + totalDays);
        return result;
    }

    /**
     * Get days in a Nepali month
     */
    function daysInNepaliMonth(npYear, npMonth) {
        if (!monthData[npYear]) return 30; // fallback
        return monthData[npYear][npMonth - 1];
    }

    /**
     * Get first day of week (0=Sun) for a Nepali month
     */
    function firstDayOfNepaliMonth(npYear, npMonth) {
        const gDate = nepaliToGregorian(npYear, npMonth, 1);
        if (!gDate) return 0;
        return gDate.getDay(); // 0=Sunday, 6=Saturday
    }

    /**
     * Format a Gregorian date string to Nepali date string
     * @param {string} gregorianDateStr "YYYY-MM-DD"
     * @param {string} format 'long'|'short'|'numeric'
     */
    function formatNepaliDate(gregorianDateStr, format = 'long') {
        if (!gregorianDateStr) return '';
        const np = gregorianToNepali(gregorianDateStr);
        if (!np) return gregorianDateStr;

        switch (format) {
            case 'numeric':
                return `${np.year}-${String(np.month).padStart(2, '0')}-${String(np.day).padStart(2, '0')}`;
            case 'short':
                return `${np.day} ${NEPALI_MONTH_ABBR[np.month]} ${np.year} BS`;
            case 'long':
            default:
                return `${np.day} ${NEPALI_MONTHS[np.month]} ${np.year} BS`;
        }
    }

    /**
     * Get today's Nepali date
     */
    function todayNepali() {
        return gregorianToNepali(new Date());
    }

    /**
     * Get month name
     */
    function getMonthName(monthNum) {
        return NEPALI_MONTHS[monthNum] || '';
    }

    /**
     * Get all month names
     */
    function getAllMonthNames() {
        return NEPALI_MONTHS;
    }

    /**
     * Get the Gregorian "YYYY-MM-DD" string from a Nepali date
     */
    function nepaliToGregorianStr(npYear, npMonth, npDay) {
        const gDate = nepaliToGregorian(npYear, npMonth, npDay);
        if (!gDate) return null;
        const y = gDate.getFullYear();
        const m = String(gDate.getMonth() + 1).padStart(2, '0');
        const d = String(gDate.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    // Public API
    return {
        gregorianToNepali,
        nepaliToGregorian,
        nepaliToGregorianStr,
        daysInNepaliMonth,
        firstDayOfNepaliMonth,
        formatNepaliDate,
        todayNepali,
        getMonthName,
        getAllMonthNames,
        NEPALI_MONTHS,
        NEPALI_MONTH_ABBR,
        monthData,
    };
})();
