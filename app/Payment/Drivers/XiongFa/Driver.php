<?php

declare(strict_types=1);

namespace App\Payment\Drivers\XiongFa;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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

    protected string $signField = 'sign';

    protected string $notifySuccessText = 'SUCCESS';

    protected string $notifyFailText = 'FAIL';

    /**
     * ============================================
     *  代收接口
     * ============================================
     */

    /**
     * 创建代收订单, 返回支付网址
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function orderCreate(RequestInterface $request): ResponseInterface
    {
        return make(OrderCreate::class, ['config' => $this->config])->request($request);
    }

    /**
     * 三方回調通知, 更新訂單
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function orderNotify(RequestInterface $request): ResponseInterface
    {
        return make(OrderNotify::class, ['config' => $this->config])->request($request);
    }

    /**
     * 查詢訂單(交易), 返回訂單明細
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function orderQuery(RequestInterface $request): ResponseInterface
    {
        return make(OrderQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function mockNotify(string $orderNo): ResponseInterface
    {
        return make(MockNotify::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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
        // todo 请依据三方逻辑订制状态转换规则
        // 三方支付状态: "PAY_STATUS_SUCCESS"支付成功;"PAY_STATUS_NOT_PAY"未支付;"PAY_STATUS_PAY_FAILED"支付失败;"PAY_STATUS_GENERATE_FAILED"下单失败,未出码
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'PAY_STATUS_NOT_PAY' => '1',
            'PAY_STATUS_SUCCESS' => '2',
            default => '4',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        if ('00' != $code) {
            // todo 请依据三方逻辑订制回调失败返回内容
            return response()->raw($this->notifyFailText);
        }

        // todo 请依据三方逻辑订制回调成功返回内容
        return response()->raw($this->notifySuccessText);
    }

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 以下为范本, 请依据三方签名逻辑订制
        // 1. 字典排序
        ksort($data);

        // 2. 排除空值欄位參與簽名
        $tempData = [];

        foreach ($data as $key => $value) {
            if ('' != $value) {
                $tempData[$key] = $value;
            }
        }

        // 3. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($tempData));

        // 4. $tempStr 拼接密鑰
        $tempStr .= '&key=' . $signatureKey;

        $this->logger->info('xiongfa 签名串', compact('tempStr'));

        // 5. sign = $tempStr 進行 MD5 後轉為大寫
        return strtoupper(md5($tempStr));
    }
}
