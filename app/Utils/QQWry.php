<?php

namespace App\Utils;

class QQWry
{
    private $fp = null;
    private $firstip = 0;
    private $lastip = 0;
    private $totalip = 0;

    public function __construct($filename = null)
    {
        $filename = $filename ?: storage_path('app/qqwry.dat');

        if (!is_file($filename) || !is_readable($filename)) {
            return;
        }

        $this->fp = fopen($filename, 'rb');

        if ($this->fp !== false) {
            $this->firstip = $this->getLong();
            $this->lastip = $this->getLong();
            $this->totalip = ($this->lastip - $this->firstip) / 7;
        }
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }
    }

    private function getLong()
    {
        $result = unpack('Vlong', fread($this->fp, 4));
        return $result['long'];
    }

    private function getLong3()
    {
        $result = unpack('Vlong', fread($this->fp, 3) . chr(0));
        return $result['long'];
    }

    private function packIp($ip)
    {
        return pack('N', (int) sprintf('%u', ip2long($ip)));
    }

    private function getString($data = '')
    {
        while (($char = fread($this->fp, 1)) !== false && strlen($char) > 0 && ord($char) > 0) {
            $data .= $char;
        }

        return $data;
    }

    private function getArea()
    {
        $byte = fread($this->fp, 1);

        if ($byte === false || $byte === '') {
            return '';
        }

        switch (ord($byte)) {
            case 0:
                return '';
            case 1:
            case 2:
                fseek($this->fp, $this->getLong3());
                return $this->getString();
            default:
                return $this->getString($byte);
        }
    }

    public function getLocation($ip)
    {
        if (!$this->fp || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $location = [
            'ip' => $ip,
            'beginip' => '',
            'endip' => '',
            'country' => '',
            'area' => ''
        ];

        $packedIp = $this->packIp($ip);

        $l = 0;
        $u = $this->totalip;
        $findip = $this->lastip;

        while ($l <= $u) {
            $i = floor(($l + $u) / 2);
            fseek($this->fp, $this->firstip + $i * 7);

            $beginip = strrev(fread($this->fp, 4));

            if ($packedIp < $beginip) {
                $u = $i - 1;
            } else {
                fseek($this->fp, $this->getLong3());
                $endip = strrev(fread($this->fp, 4));

                if ($packedIp > $endip) {
                    $l = $i + 1;
                } else {
                    $findip = $this->firstip + $i * 7;
                    break;
                }
            }
        }

        fseek($this->fp, $findip);

        $location['beginip'] = long2ip($this->getLong());
        $offset = $this->getLong3();

        fseek($this->fp, $offset);
        $location['endip'] = long2ip($this->getLong());

        $byte = fread($this->fp, 1);

        if ($byte === false || $byte === '') {
            return $location;
        }

        switch (ord($byte)) {
            case 1:
                $countryOffset = $this->getLong3();
                fseek($this->fp, $countryOffset);
                $byte = fread($this->fp, 1);

                if ($byte !== false && $byte !== '' && ord($byte) === 2) {
                    fseek($this->fp, $this->getLong3());
                    $location['country'] = $this->getString();
                    fseek($this->fp, $countryOffset + 4);
                    $location['area'] = $this->getArea();
                } else {
                    $location['country'] = $this->getString($byte ?: '');
                    $location['area'] = $this->getArea();
                }
                break;

            case 2:
                fseek($this->fp, $this->getLong3());
                $location['country'] = $this->getString();
                fseek($this->fp, $offset + 8);
                $location['area'] = $this->getArea();
                break;

            default:
                $location['country'] = $this->getString($byte);
                $location['area'] = $this->getArea();
                break;
        }

        if ($location['country'] === ' CZ88.NET') {
            $location['country'] = '未知';
        }

        if ($location['area'] === ' CZ88.NET') {
            $location['area'] = '';
        }

        return $location;
    }
}
