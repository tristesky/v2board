<?php

namespace App\Services;

use App\Utils\QQWry;
use Illuminate\Support\Facades\Cache;

class IpLocationService
{
    public static function lookup($ip)
    {
        $ip = trim((string) $ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '未知';
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'IPv6 暂不支持本地纯真库';
        }

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false
        ) {
            return '内网/保留地址';
        }

        if (!is_file(storage_path('app/qqwry.dat'))) {
            return '未安装 QQWry 数据库';
        }

        return Cache::remember('online_ip_location_' . $ip, 86400, function () use ($ip) {
            $reader = new QQWry();
            $location = $reader->getLocation($ip);

            if (!$location) {
                return is_file(storage_path('app/qqwry.dat')) ? '未知' : '未安装 QQWry 数据库';
            }

            $country = self::gbkToUtf8($location['country'] ?? '');
            $area = self::gbkToUtf8($location['area'] ?? '');

            $text = trim($country . ' ' . $area);

            return $text !== '' ? $text : '未知';
        });
    }

    private static function gbkToUtf8($text)
    {
        $text = (string) $text;

        if ($text === '') {
            return '';
        }

        $converted = @iconv('GBK', 'UTF-8//IGNORE', $text);

        return $converted !== false ? $converted : $text;
    }
}
