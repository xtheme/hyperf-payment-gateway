<?php

declare(strict_types=1);

namespace App\Payment\Contracts;

interface OrderDriverInterface
{
    public function createOrder(string $orderNo, array $data, string $type);

    public function isOrderExists(string $orderNo, string $type = 'order');

    public function getOrder(string $orderNo, string $type = 'order');

    public function updateOrder(string $orderNo, array $data, string $type = 'order');

    public function getFormData(string $orderNo, string $type = 'form');

    public function createFormData(string $orderNo, array $data, string $type = 'form');
}
