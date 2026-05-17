<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServerAnytls;
use App\Models\ServerHysteria;
use App\Models\ServerShadowsocks;
use App\Models\ServerTrojan;
use App\Models\ServerTuic;
use App\Models\ServerV2node;
use App\Models\ServerVless;
use App\Models\ServerVmess;
use App\Models\User;
use App\Services\IpLocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OnlineIpController extends Controller
{

    public function fetch(Request $request)
    {
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = (int) $request->input('pageSize', 20);

        if ($pageSize < 1) {
            $pageSize = 20;
        }

        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $filterUserId = trim((string) $request->input('user_id', ''));
        $filterEmail = trim((string) $request->input('email', ''));
        $filterIp = trim((string) $request->input('ip', ''));

        $users = $this->getTargetUsers($filterUserId, $filterEmail);

        if ($users->isEmpty()) {
            return response([
                'data' => [],
                'total' => 0
            ]);
        }

        $serverNameMap = $this->buildServerNameMap();
        $rows = [];

        foreach ($users as $user) {
            $cache = Cache::get('ALIVE_IP_USER_' . $user->id);

            if (!is_array($cache)) {
                continue;
            }

            foreach ($cache as $nodeKey => $nodeData) {
                if ($nodeKey === 'alive_ip' || !is_array($nodeData) || empty($nodeData['aliveips'])) {
                    continue;
                }

                [$nodeType, $nodeId] = $this->parseNodeKey($nodeKey);

                foreach ($nodeData['aliveips'] as $rawIp) {
                    $ipInfo = $this->parseRawIp($rawIp);

                    if (!$ipInfo['ip']) {
                        continue;
                    }

                    if ($filterIp !== '' && strpos($ipInfo['ip'], $filterIp) === false) {
                        continue;
                    }

                    $lastUpdateAt = (int) ($nodeData['lastupdateAt'] ?? 0);

                    $rows[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'node_key' => $nodeKey,
                        'node_type' => $nodeType,
                        'node_id' => $nodeId,
                        'node_name' => $serverNameMap[$nodeType][$nodeId] ?? '',
                        'ip' => $ipInfo['ip'],
                        'ip_tag' => $ipInfo['tag'],
                        'location' => IpLocationService::lookup($ipInfo['ip']),
                        'last_update_at' => $lastUpdateAt,
                        'last_update_time' => $lastUpdateAt ? date('Y-m-d H:i:s', $lastUpdateAt) : '',
                        'age_seconds' => $lastUpdateAt ? max(0, time() - $lastUpdateAt) : null
                    ];
                }
            }
        }

        usort($rows, function ($a, $b) {
            return ($b['last_update_at'] <=> $a['last_update_at'])
                ?: ($a['user_id'] <=> $b['user_id']);
        });

        $total = count($rows);
        $offset = ($current - 1) * $pageSize;
        $data = array_slice($rows, $offset, $pageSize);

        return response([
            'data' => array_values($data),
            'total' => $total
        ]);
    }

    private function getTargetUsers($filterUserId, $filterEmail)
    {
        $query = User::select(['id', 'email'])->orderBy('id', 'asc');

        if ($filterUserId !== '') {
            return $query->where('id', (int) $filterUserId)->get();
        }

        if ($filterEmail !== '') {
            return $query->where('email', 'like', '%' . $filterEmail . '%')->get();
        }

        // 稳定优先：和后台用户管理页保持一致，逐个用户读取 Cache::get('ALIVE_IP_USER_{uid}')。
        return $query->get();
    }

    private function parseRawIp($rawIp)
    {
        $rawIp = trim((string) $rawIp);

        if ($rawIp === '') {
            return [
                'ip' => '',
                'tag' => ''
            ];
        }

        $parts = explode('_', $rawIp, 2);
        $ip = trim($parts[0]);
        $tag = isset($parts[1]) ? trim($parts[1]) : '';

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return [
                'ip' => '',
                'tag' => $tag
            ];
        }

        return [
            'ip' => $ip,
            'tag' => $tag
        ];
    }

    private function parseNodeKey($nodeKey)
    {
        $nodeKey = (string) $nodeKey;

        $knownTypes = [
            'shadowsocks',
            'hysteria',
            'anytls',
            'v2node',
            'trojan',
            'vmess',
            'vless',
            'tuic'
        ];

        foreach ($knownTypes as $type) {
            if (strpos($nodeKey, $type) === 0) {
                return [
                    $type,
                    (int) substr($nodeKey, strlen($type))
                ];
            }
        }

        return [
            $nodeKey,
            0
        ];
    }

    private function buildServerNameMap()
    {
        $models = [
            'shadowsocks' => ServerShadowsocks::class,
            'vmess' => ServerVmess::class,
            'v2ray' => ServerVmess::class,
            'trojan' => ServerTrojan::class,
            'vless' => ServerVless::class,
            'tuic' => ServerTuic::class,
            'hysteria' => ServerHysteria::class,
            'anytls' => ServerAnytls::class,
            'v2node' => ServerV2node::class
        ];

        $map = [];

        foreach ($models as $type => $model) {
            if (!class_exists($model)) {
                continue;
            }

            $map[$type] = $model::query()
                ->select(['id', 'name'])
                ->get()
                ->pluck('name', 'id')
                ->toArray();
        }

        return $map;
    }
}
