<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateIp2Location extends Command
{
    protected $signature = 'online-ip:update-ip2location
        {--token= : IP2Location download token}
        {--file=DB11LITEBINIPV6 : IP2Location database code}
        {--dest= : Target directory for BIN file}';

    protected $description = 'Update IP2Location LITE IPv6 BIN database for online IP location';

    public function handle()
    {
        $token = $this->option('token') ?: env('IP2LOCATION_TOKEN');
        $fileCode = $this->option('file') ?: 'DB11LITEBINIPV6';
        $dest = $this->option('dest') ?: storage_path('app');

        if (!$token) {
            $this->error('IP2Location token is empty. Use --token=xxx or set IP2LOCATION_TOKEN in .env');
            return 1;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $curl = trim((string) shell_exec('command -v curl'));
        $unzip = trim((string) shell_exec('command -v unzip'));

        if ($curl === '') {
            $this->error('curl not found. Please install curl first.');
            return 1;
        }

        if ($unzip === '') {
            $this->error('unzip not found. Please install unzip first.');
            $this->line('Ubuntu/Debian: apt update && apt install -y unzip');
            return 1;
        }

        $tmpDir = sys_get_temp_dir() . '/ip2location-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        $zipPath = $tmpDir . '/ip2location.zip';

        mkdir($tmpDir, 0700, true);

        $url = 'https://www.ip2location.com/download?token=' . rawurlencode($token)
            . '&file=' . rawurlencode($fileCode);

        $this->info('Downloading IP2Location database...');
        $this->line('Database code: ' . $fileCode);

        $cmd = escapeshellcmd($curl)
            . ' -L --fail --silent --show-error'
            . ' -o ' . escapeshellarg($zipPath)
            . ' ' . escapeshellarg($url);

        passthru($cmd, $code);

        if ($code !== 0 || !is_file($zipPath) || filesize($zipPath) < 1024 * 1024) {
            $this->error('Download failed or file is too small.');
            $this->cleanup($tmpDir);
            return 1;
        }

        $cmd = escapeshellcmd($unzip)
            . ' -o ' . escapeshellarg($zipPath)
            . ' -d ' . escapeshellarg($tmpDir);

        passthru($cmd, $code);

        if ($code !== 0) {
            $this->error('Unzip failed.');
            $this->cleanup($tmpDir);
            return 1;
        }

        $binFiles = glob($tmpDir . '/*.BIN');

        if (!$binFiles) {
            $this->error('No BIN file found in downloaded archive.');
            $this->cleanup($tmpDir);
            return 1;
        }

        $source = $binFiles[0];
        $target = rtrim($dest, '/') . '/IP2LOCATION-LITE-DB11.IPV6.BIN';

        copy($source, $target);
        chmod($target, 0644);

        $this->info('IP2Location database updated successfully.');
        $this->line('Path: ' . $target);
        $this->line('Size: ' . round(filesize($target) / 1024 / 1024, 2) . ' MB');

        $this->cleanup($tmpDir);

        return 0;
    }

    private function cleanup($dir)
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
