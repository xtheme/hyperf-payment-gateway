<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jbp;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface
{
    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'key';

    protected string $notifySuccessText = '1';

    protected string $notifyFailText = '0';

    /**
     * ============================================
     *  代收接口
     * ============================================
     */

    /**
     * 创建代收订单, 返回支付网址
     */
    public function orderCreate(RequestInterface $request): ResponseInterface
    {
        return make(OrderCreate::class, ['config' => $this->config])->request($request);
    }

    /**
     * 三方回調通知, 更新訂單
     */
    public function orderNotify(RequestInterface $request): ResponseInterface
    {
        return make(OrderNotify::class, ['config' => $this->config])->request($request);
    }

    /**
     * 查詢訂單(交易), 返回訂單明細
     */
    public function orderQuery(RequestInterface $request): ResponseInterface
    {
        return make(OrderQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function mockNotify(string $orderNo): ResponseInterface
    {
        return make(MockNotify::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockQuery(string $orderNo): ResponseInterface
    {
        return make(MockQuery::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 1=处理成功-success, 2=处理失败-failed, 3=处理中pending
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '3' => '1',
            '1' => '2',
            default => '4',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */
    public function responsePlatform(string $code = '', string $orderNo = '', string $tradeNo = ''): ResponseInterface
    {
        $data = [
            'company_order_num' => $orderNo, // 商户订单号
            'mownecum_order_num' => $tradeNo, // 三方订单号
            'status' => $this->notifyFailText,
            'error_msg' => '',
        ];

        // 集成网关返回
        if ('00' == $code) {
            $data['status'] = $this->notifySuccessText;
        } else {
            $data['error_msg'] = '支付失败';
        }

        return response()->json($data);
    }

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        unset($data['error_msg'], $data['terminal']);

        // 1. 依據組合傳參拼接字串
        $tempStr = implode('', $data);

        // 2. $tempStr 拼接 md5(密鑰)
        $tempStr = md5($signatureKey) . $tempStr;

        // 3. 二次 md5
        return md5($tempStr);
    }
}
