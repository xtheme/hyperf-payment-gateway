<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\ApiException;
use App\Payment\Contracts\OrderDriverInterface;
use App\Payment\Contracts\PaymentGatewayInterface;
use App\Payment\Drivers\AbstractDriver;
use App\Request\OrderCreateRequest;
use App\Request\OrderQueryRequest;
use FriendsOfHyperf\Cache\Facade\Cache;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Stringable\Str;
use Hyperf\ViewEngine\Contract\ViewInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

use function Hyperf\Config\config;
use function Hyperf\Support\make;
use function Hyperf\ViewEngine\view;

class PaymentController extends BaseController
{
    #[Inject]
    protected PaymentGatewayInterface $platformGateway;

    public function create(OrderCreateRequest $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'orderCreate');
    }

    public function query(OrderQueryRequest $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'orderQuery');
    }

    /**
     * @return ResponseInterface|PsrResponseInterface ;
     */
    public function notify(RequestInterface $request): ResponseInterface|PsrResponseInterface
    {
        return $this->getDriverMethod($request, 'orderNotify');
    }

    public function mock(RequestInterface $request): mixed
    {
        $action = $request->route('action') ?? '';
        $orderNo = $request->route('order_no') ?? '';

        $orderDriver = make(config('payment.order_driver'));
        $order = $orderDriver->getOrder($orderNo);

        if (!$order) {
            throw new ApiException('訂單不存在');
        }

        $driverName = $order['payment_platform'] ?? '';

        return match ($action) {
            'query' => $this->platformGateway->getDriver($driverName)->mockQuery($orderNo),
            'notify' => $this->platformGateway->getDriver($driverName)->mockNotify($orderNo),
            default => throw new ApiException('Unknown mock method'),
        };
    }

    /**
     * 利用送出表單方式轉跳到三方收銀台頁面 (綠界)
     */
    public function redirect(RequestInterface $request): ViewInterface
    {
        $orderNo = $request->route('order_no') ?? '';

        if (!$orderNo) {
            throw new ApiException('缺少必要的訂單號');
        }

        // GsPay 緩存了整個表單
        if (Str::startsWith($orderNo, 'gspay')) {
            $viewData['html'] = Cache::get('GsPay_deposit_form:' . $orderNo);

            return view('redirect-gspay', $viewData);
        }

        /** @var AbstractDriver $orderDriver */
        $orderDriver = make(config('payment.order_driver'));

        $order = $orderDriver->getOrder($orderNo);
        $form = $orderDriver->getFormData($orderNo);

        $viewData = [
            'form' => $form,
            'create_url' => $order['create_url'],
        ];

        return view('redirect', $viewData);
    }

    /**
     * 透過訂單查詢, 顯示存款銀行資訊頁面
     * http://127.0.0.1:9503/cashier/xinda_61449812
     */
    public function cashier(RequestInterface $request): ViewInterface
    {
        $orderNo = $request->route('order_no') ?? '';

        try {
            /** @var OrderDriverInterface $driver */
            $driver = container()->get(config('payment.order_driver'));
            $order = $driver->getOrder($orderNo);
        } catch (\Exception $e) {
            return view('404');
        }

        $viewData = [
            'order_no' => $orderNo,
            'amount' => bcdiv($order['amount'], '100', 2), // 分轉元
            'payee_name' => $order['payee_name'] ?? '',
            'payee_bank_name' => $order['payee_bank_name'] ?? '',
            'payee_bank_branch_name' => $order['payee_bank_branch_name'] ?? '',
            'payee_bank_account' => $order['payee_bank_account'] ?? '',
        ];

        return view('cashier', $viewData);
    }
}
