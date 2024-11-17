<?php

class JadwalShalat {

   protected function get_prayer_times() {
        // Input data
        $BT = 95.31745938953232; // Bujur Tempat (Banda Aceh)
        $LT = 5.553953305108352; // Lintang Tempat
        $TT = 10; // Tinggi Tempat dalam meter
        $IH = 2 / 60; // Ihtiyat (2 menit dalam jam desimal)
        $zoneMeridian = 105; // Meridian standar untuk UTC+7 (WIB)

        // Tanggal perhitungan (17 November 2024)
        $day = date('d');
        $month = date('m');
        $year = date('Y');

        // Menghitung DM dan PWM
        $solarParams = $this->calculateSolarParameters($day, $month, $year);
        $DM = $solarParams['DM'];
        $PWM = $solarParams['PWM'];

        // Menghitung KWD
        $KWD = $this->calculateKWD($BT, $zoneMeridian);

        // Menghitung KU
        $KU = $this->calculateKU($TT);

        // Menghitung Waktu Dzuhur
        $Dzuhur = 12 - $PWM - $KWD + $IH;

        // Menghitung SWM untuk Subuh dan Isya (TM = -20°, -18°)
        $SWM_Subuh = $this->calculateSWM(-20 + $KU, $LT, $DM);
        $SWM_Isya = $this->calculateSWM(-18 + $KU, $LT, $DM);

        // Menghitung Waktu Subuh
        $Subuh = 12 - $PWM - ($SWM_Subuh / 15) - $KWD + $IH;

        // Menghitung Waktu Isya
        $Isya = 12 - $PWM + ($SWM_Isya / 15) - $KWD + $IH;

        // Menghitung SWM untuk Terbit dan Maghrib (TM = -0°50')
        $TM_Terbit = -0.8333 + $KU; // -0°50' = -0.8333°
        $SWM_Terbit = $this->calculateSWM($TM_Terbit, $LT, $DM);

        // Menghitung Waktu Terbit
        $Terbit = 12 - $PWM - ($SWM_Terbit / 15) - $KWD - $IH;

        // Menghitung Waktu Maghrib
        $Maghrib = 12 - $PWM + ($SWM_Terbit / 15) - $KWD + $IH;

        // Menghitung SWM untuk Ashar (TM khusus)
        $TM_Ashar = $this->rad2deg_custom(atan(1 + tan(abs($this->deg2rad_custom($LT - $DM)))));
        $SWM_Ashar = $this->calculateSWM(90 - $TM_Ashar, $LT, $DM);

        // Menghitung Waktu Ashar
        $Ashar = 12 - $PWM + ($SWM_Ashar / 15) - $KWD + $IH;

        // Menghitung Waktu Imsak (10 menit sebelum Subuh)
        $Imsak = $Subuh - (10 / 60);

        // Menghitung Waktu Dhuha (sekitar 15 menit setelah Terbit)
        $Dhuha = $Terbit + (29 / 60);

        return [
            'imsak' =>  $this->timeDecimalToTime($Imsak),
            'subuh' =>  $this->timeDecimalToTime($Subuh),
            'terbit' =>  $this->timeDecimalToTime($Terbit),
            'dhuha' =>  $this->timeDecimalToTime($Dhuha),
            'dzuhur' =>  $this->timeDecimalToTime($Dzuhur),
            'ashar' =>  $this->timeDecimalToTime($Ashar),
            'maghrib' =>  $this->timeDecimalToTime($Maghrib),
            'isya' =>  $this->timeDecimalToTime($Isya)

        ];
    }

    // Fungsi konversi derajat ke radian
    protected function deg2rad_custom($degree) {
        return $degree * (M_PI / 180);
    }

    // Fungsi konversi radian ke derajat
    protected function rad2deg_custom($radian) {
        return $radian * (180 / M_PI);
    }

    // Fungsi untuk menghitung DM dan PWM
    protected function calculateSolarParameters($day, $month, $year) {
        // Hari Julian
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        $A = floor($year / 100);
        $B = 2 - $A + floor($A / 4);
        $JD = floor(365.25 * ($year + 4716)) + floor(30.6001 * ($month + 1)) + $day + $B - 1524.5;

        // Waktu T
        $T = ($JD - 2451545.0) / 36525.0;

        // Deklinasi Matahari (DM)
        $L0 = 280.46646 + 36000.76983 * $T + 0.0003032 * pow($T, 2);
        $M = 357.52911 + 35999.05029 * $T - 0.0001537 * pow($T, 2);
        $e = 0.016708634 - 0.000042037 * $T - 0.0000001267 * pow($T, 2);
        $C = (1.914602 - 0.004817 * $T - 0.000014 * pow($T, 2)) * sin($this->deg2rad_custom($M))
        + (0.019993 - 0.000101 * $T) * sin($this->deg2rad_custom(2 * $M))
        + 0.000289 * sin($this->deg2rad_custom(3 * $M));
        $theta = $L0 + $C;
        $omega = 125.04 - 1934.136 * $T;
        $lambda = $theta - 0.00569 - 0.00478 * sin($this->deg2rad_custom($omega));
        $epsilon0 = 23 + 26/60 + 21.448/3600 - (46.8150 * $T + 0.00059 * pow($T, 2) - 0.001813 * pow($T, 3))/3600;
        $epsilon = $epsilon0 + 0.00256 * cos($this->deg2rad_custom($omega));
        $DM = $this->rad2deg_custom(asin(sin($this->deg2rad_custom($epsilon)) * sin($this->deg2rad_custom($lambda))));

        // Perata Waktu Matahari (PWM)
        $E = $M - 0.0057183 - $lambda + 180;
        $E = fmod($E + 360, 360);
        if ($E > 180) {
            $E -= 360;
        }
        $PWM = $E / 15;

        return array('DM' => $DM, 'PWM' => $PWM);
    }

    // Fungsi menghitung KWD
    protected function calculateKWD($BT, $zoneMeridian) {
        return ($BT - $zoneMeridian) / 15;
    }

    // Fungsi menghitung KU
    protected function calculateKU($TT) {
        return -0.035333333 * sqrt($TT); // -0°1'76'' = -0.035333333°
    }

    // Fungsi menghitung SWM
    protected function calculateSWM($TM, $LT, $DM) {
        $numerator = sin($this->deg2rad_custom($TM)) - sin($this->deg2rad_custom($LT)) * sin($this->deg2rad_custom($DM));
        $denominator = cos($this->deg2rad_custom($LT)) * cos($this->deg2rad_custom($DM));
        $SWM = $this->rad2deg_custom(acos($numerator / $denominator));
        return $SWM;
    }

    // Fungsi konversi waktu desimal ke jam dan menit
    protected function timeDecimalToTime($time) {
        $hours = floor($time);
        $minutes = floor(($time - $hours) * 60);
        return sprintf("%02d:%02d", $hours-7, $minutes);
    }
}
