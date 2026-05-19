<?php

namespace App\Payments;

use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeCheckout {
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'currency' => [
                'label' => '货币单位',
                'description' => '请使用符合 ISO 4217 标准的三位字母，例如 USD, GBP',
                'type' => 'input',
            ],
            'stripe_sk_live' => [
                'label' => 'SK_LIVE',
                'description' => 'Stripe 密钥',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'WebHook 密钥签名',
                'description' => '以 whsec_ 开头的签名密钥',
                'type' => 'input',
            ],
            'stripe_custom_field_name' => [
                'label' => '自定义字段名称',
                'description' => '例如可设置为“联系方式”，以便及时与客户取得联系',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $currency = $this->config['currency'];
        $exchange = $this->exchange('CNY', strtoupper($currency));
        
        if (!$exchange) {
            abort(500, __('Currency conversion has timed out, please try again later'));
        }

        // 语法优化：使用空合并运算符，修复拼写错误
        $customFieldName = $this->config['stripe_custom_field_name'] ?? 'Contact Information';

        $params = [
            'success_url' => $order['return_url'],
            'cancel_url' => $order['return_url'],
            'client_reference_id' => $order['trade_no'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => 'Order #' . $order['trade_no'] // 略微优化展示名称
                        ],
                        'unit_amount' => floor($order['total_amount'] * $exchange)
                    ],
                    'quantity' => 1
                ]
            ],
            'mode' => 'payment',
            'invoice_creation' => ['enabled' => true],
            'phone_number_collection' => ['enabled' => false],
            'custom_fields' => [
                [
                    'key' => 'contactinfo',
                    'label' => ['type' => 'custom', 'custom' => $customFieldName . '（可留空）'],
                    'type' => 'text',
                    'optional' => true,
                ],
            ],
        ];

        Stripe::setApiKey($this->config['stripe_sk_live']);
        
        try {
            $session = Session::create($params);
        } catch (\Exception $e) {
            info('Stripe Checkout Error: ' . $e->getMessage());
            // 修复了 getMessage 缺少括号的致命错误
            abort(500, "Failed to create order. Error: {$e->getMessage()}");
        }

        return [
            'type' => 1, 
            'data' => $session->url
        ];
    }

    public function notify($params)
    {
        \Stripe\Stripe::setApiKey($this->config['stripe_sk_live']);
        
        try {
            // 优化了底层 payload 获取方式
            $payload = request()->getContent() ?: file_get_contents('php://input');
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '',
                $this->config['stripe_webhook_key']
            );
        } catch (\Stripe\Error\SignatureVerification $e) {
            abort(400, 'Invalid signature');
        } catch (\UnexpectedValueException $e) {
            abort(400, 'Invalid payload');
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $object = $event->data->object;
                if ($object->payment_status === 'paid') {
                    return [
                        'trade_no' => $object->client_reference_id,
                        'callback_no' => $object->payment_intent
                    ];
                }
                break;
            case 'checkout.session.async_payment_succeeded':
                $object = $event->data->object;
                return [
                    'trade_no' => $object->client_reference_id,
                    'callback_no' => $object->payment_intent
                ];
                break;
            default:
                // 收到非目标事件时直接返回 success，避免 Stripe Webhook 面板积压报错
                return('success');
        }
        
        return('success');
    }

    private function exchange($from, $to)
    {
        // 增加流上下文，设置 3 秒超时限制，防止外部 API 挂掉时拖死 PHP 进程
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 3
            ]
        ]);

        try {
            $result = @file_get_contents("https://api.exchangerate-api.com/v4/latest/{$from}", false, $ctx);
            if ($result === false) {
                return false;
            }
            $result = json_decode($result, true);
            return $result['rates'][$to] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }
}