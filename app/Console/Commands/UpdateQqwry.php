<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateQqwry extends Command
{
    protected $signature = 'online-ip:update-qqwry';

    protected $description = 'Download or update QQWry IP location database for online IP location lookup';

    public function handle()
    {
        $url = 'https://raw.githubusercontent.com/out0fmemory/qqwry.dat/master/qqwry_lastest.dat';
        $target = storage_path('app/qqwry.dat');
        $tmp = $target . '.tmp';

        $this->info('Downloading QQWry database...');
        $this->line($url);

        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->error('Failed to create directory: ' . $dir);
            return 1;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 60,
                'follow_location' => 1,
                'user_agent' => 'V2Board Online IP Updater'
            ]
        ]);

        $data = @file_get_contents($url, false, $context);

        if ($data === false || strlen($data) < 1024 * 1024) {
            $this->error('Failed to download QQWry database, or downloaded file is too small.');
            return 1;
        }

        if (file_put_contents($tmp, $data) === false) {
            $this->error('Failed to write temporary file: ' . $tmp);
            return 1;
        }

        if (!rename($tmp, $target)) {
            @unlink($tmp);
            $this->error('Failed to move temporary file to: ' . $target);
            return 1;
        }

        @chmod($target, 0644);

        $this->info('QQWry database updated successfully.');
        $this->line('Path: ' . $target);
        $this->line('Size: ' . $this->formatBytes(filesize($target)));

        return 0;
    }

    private function formatBytes($bytes)
    {
        $bytes = (int) $bytes;

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
