<?php

declare(strict_types=1);

namespace App\Payment\OrderDrivers;

use App\Exception\ApiException;
use App\Model\Order;
use App\Payment\Contracts\OrderDriverInterface;
use Hyperf\Stringable\Str;

class DbOrder extends AbstractOrder implements OrderDriverInterface
{
    public function createOrder(string $orderNo, array $data, string $type = 'order'): void
    {
        $header_params = $data['header_params'] ? json_encode($data['header_params'], JSON_UNESCAPED_SLASHES) : '{}';
        $body_params = $data['body_params'] ? json_encode($data['body_params'], JSON_UNESCAPED_SLASHES) : '{}';

        $order = [
            'type' => $type,
            'order_no' => $orderNo,
            'trade_no' => $data['trade_no'] ?? '',
            'status' => $data['status'] ?? '0',
            'site_id' => $data['site_id'],
            'payment_platform' => $data['payment_platform'],
            'payment_channel' => $data['payment_channel'],
            'merchant_id' => $data['merchant_id'],
            'merchant_key' => $data['merchant_key'],
            'header_params' => $header_params,
            'body_params' => $body_params,
            'create_url' => $data['endpoint_url'] ?? $data['create_url'], // 渠道创建訂單地址, 因为是在创建订单时初始化, 所以配置 endpoint_url
            'query_url' => $data['query_url'] ?? '',                     // 渠道查詢訂單地址, 假设为空表示三方回调时不做二次校验
            'callback_url' => $data['callback_url'],                        // 回调集成网关地址
            'amount' => $data['amount'],
            'real_amount' => $data['real_amount'] ?? '0',
            'fee' => $data['fee'] ?? '0',
            'commission' => $data['commission'] ?? '0',
            'currency' => Str::upper($data['currency']) ?? 'CNY',
            'bank_name' => $data['bank_name'] ?? '',
            'bank_code' => $data['bank_code'] ?? '',
            'bank_branch_name' => $data['bank_branch_name'] ?? '',
            'bank_branch_code' => $data['bank_branch_code'] ?? '',
            'bank_account' => $data['bank_account'] ?? '',
            'user_name' => $data['user_name'] ?? '',
            'user_phone' => $data['user_phone'] ?? '',
            'user_id' => $data['user_id'] ?? '',
            'payee_name' => $data['payee_name'] ?? '',
            'payee_bank_name' => $data['payee_bank_name'] ?? '',
            'payee_bank_branch_name' => $data['payee_bank_branch_name'] ?? '',
            'payee_bank_account' => $data['payee_bank_account'] ?? '',
            'payee_nonce' => '',
            'remark' => '',
            'lock' => 0, // 回調成功後鎖定訂單, 防止重複回調
            'created_at' => $this->getServerDateTime(),
            'updated_at' => $this->getServerDateTime(),
        ];

        $log = sprintf('%s: %s createOrder', $type, $orderNo);
        $this->logger->info($log, $order);

        Order::insert($order);
    }

    public function isOrderExists(string $orderNo, string $type = 'order'): bool
    {
        return Order::where('order_no', $orderNo)
            ->where('type', $type)
            ->exists();
    }

    public function getOrder(string $orderNo, string $type = 'order'): Order
    {
        /** @var Order $order */
        $order = Order::where('order_no', $orderNo)
            ->where('type', $type)
            ->first();

        if (!$order) {
            throw new ApiException('订单记录不存在或已过期 ' . $orderNo);
        }

        return $order;
    }

    public function updateOrder(string $orderNo, array $data, string $type = 'order'): void
    {
        $order = $this->getOrder($orderNo, $type);

        if (1 == $order->lock) {
            $message = sprintf('重复回调订单 %s, 不予更新', $orderNo);
            $this->logger->warning($message);

            return;
            // throw new ApiException('重复回调订单 ' . $orderNo);
        }

        $data['updated_at'] = $this->getServerDateTime(); // 更新時間

        $log = sprintf('%s: %s updateOrder', $type, $orderNo);
        $this->logger->info($log, $data);

        if ('order' == $type && isset($data['status']) && '2' == $data['status']) {
            $data['lock'] = 1; // 回調成功後鎖定訂單, 防止重複回調
        }

        if ('withdraw' == $type && isset($data['status']) && '3' == $data['status']) {
            $data['lock'] = 1; // 回調成功後鎖定訂單, 防止重複回調
        }

        $order->update($data);
    }
}
