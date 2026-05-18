<?php

namespace App\Services;

use App\Utils\QQWry;

class IpLocationService
{
    private $qqwry;
    private $ip2location;

    public static function lookup($ip)
    {
        return (new self())->locate($ip);
    }

    public function get($ip)
    {
        return $this->locate($ip);
    }

    public function getLocation($ip)
    {
        return $this->locate($ip);
    }

    public function locate($ip)
    {
        $ip = trim((string) $ip);

        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '未知';
        }

        if ($this->isPrivateOrReservedIp($ip)) {
            return '本地/内网地址';
        }

        // IPv6：走 IP2Location LITE DB11 IPv6 BIN
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ip2locationLocation($ip) ?: 'IPv6 未知';
        }

        // IPv4：优先纯真 QQWry
        $qqwry = $this->qqwryLocation($ip);

        if ($qqwry !== '') {
            return $qqwry;
        }

        // IPv4 纯真查不到时，用 IP2Location 兜底
        return $this->ip2locationLocation($ip) ?: '未知';
    }

    private function qqwryLocation($ip)
    {
        $path = storage_path('app/qqwry.dat');

        if (!is_file($path)) {
            return '';
        }

        try {
            if (!$this->qqwry) {
                $this->qqwry = new QQWry($path);
            }

            $result = $this->qqwry->getLocation($ip);

            $country = $this->gbkToUtf8($result['country'] ?? '');
            $area = $this->gbkToUtf8($result['area'] ?? '');

            $location = trim($country . ' ' . $area);

            if ($location === '' || stripos($location, 'CZ88.NET') !== false) {
                return '';
            }

            return $location;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function ip2locationLocation($ip)
    {
        if (!class_exists('\IP2Location\Database')) {
            return '';
        }

        $path = storage_path('app/IP2LOCATION-LITE-DB11.IPV6.BIN');

        if (!is_file($path)) {
            return '';
        }

        try {
            if (!$this->ip2location) {
                $class = '\IP2Location\Database';
                $this->ip2location = new $class($path, $class::FILE_IO);
            }

            $record = $this->ip2location->lookup($ip, \IP2Location\Database::ALL);

            if (!is_array($record)) {
                return '';
            }

            $parts = [];

            foreach ([
                $record['countryName'] ?? '',
                $record['regionName'] ?? '',
                $record['cityName'] ?? '',
            ] as $part) {
                $part = $this->cleanPart($part);

                if ($part !== '' && !in_array($part, $parts, true)) {
                    $parts[] = $part;
                }
            }

            return trim(implode(' ', $parts));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function cleanPart($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-' || strtolower($value) === 'unknown') {
            return '';
        }

        if (stripos($value, 'This parameter is unavailable') !== false) {
            return '';
        }

        return $value;
    }

    private function gbkToUtf8($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $converted = @iconv('GBK', 'UTF-8//IGNORE', $value);

        return $converted !== false ? $converted : $value;
    }

    private function isPrivateOrReservedIp($ip)
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
