<?php

namespace App\Protocols;


use App\Utils\Helper;

class V2rayN
{
    public $flag = 'v2rayn';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $uri = '';

        foreach ($this->servers as $server) {
            $uri .= Helper::buildUri($this->user['uuid'], $server, [
                'vless_grpc_mode' => 'multi',
            ]);
        }
        return base64_encode($uri);
    }
}
