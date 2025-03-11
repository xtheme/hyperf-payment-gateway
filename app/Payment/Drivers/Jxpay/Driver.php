<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jxpay;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

    protected string $notifySuccessText = 'success';

    protected string $notifyFailText = 'fail';

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
     * ============================================
     *  代付接口
     * ============================================
     */

    /**
     * 创建代付订单, 返回三方交易號
     */
    public function withdrawCreate(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawCreate::class, ['config' => $this->config])->request($request);
    }

    public function withdrawNotify(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawNotify::class, ['config' => $this->config])->request($request);
    }

    public function withdrawQuery(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function mockWithdrawNotify(string $orderNo): ResponseInterface
    {
        return make(MockWithdrawNotify::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockWithdrawQuery(string $orderNo): ResponseInterface
    {
        return make(MockWithdrawQuery::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * 查詢商戶餘額
     */
    public function balance(RequestInterface $request)
    {
        return make(Balance::class, ['config' => $this->config])->request($request);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        // 三方支付状态: PENDING=待付款, PAID=已付款, MANUAL PAID=已补单
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'PENDING' => '1',
            'PAID', 'MANUAL PAID' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: PAID=出款成功, CANCELLED=出款失败
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            'PAID' => '3',
            default => '5',
        };
    }

    /**
     * 通知三方回調結果
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        if ('00' != $code) {
            // 依据三方逻辑订制回调失败返回内容
            return response()->raw($this->notifyFailText);
        }

        // 依据三方逻辑订制回调成功返回内容
        return response()->raw($this->notifySuccessText);
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 除去 userName, channelNo, payeeName, returnUrl,  storeId, storeType, tradeCode, appSecret和bankName 不参与加密，其它的参数空值null值一样参加加密
        $ignoreFields = ['userName', 'channelNo', 'payeeName', 'returnUrl', 'storeId', 'storeType', 'tradeCode', 'appSecret', 'bankName'];
        $tempData = array_filter($data, function ($key) use ($ignoreFields) {
            return !in_array($key, $ignoreFields);
        }, ARRAY_FILTER_USE_KEY);

        // 字典排序 a-z
        ksort($tempData);

        // 然后将参数按照 URL 键值对的方式拼接成 `键值对字符串`
        $tempStr = urldecode(http_build_query($tempData));

        // `键值对字符串` 拼接密钥生成 `签名源字串`
        $tempStr .= $signatureKey;

        // `签名源字串` 使用 hash 256 算法计算摘要
        $digest = hash('sha256', $tempStr);

        // 对摘要 digest 做 md5 计算，md5结果转大写后，即为最终的签名
        return strtoupper(md5($digest));
    }

    /**
     * 簽名規則
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 除去 bankBranch，memo 和 appSecret 不参与加密，其它的参数空值null值一样参加加密
        $ignoreFields = ['appSecret', 'bankBranch', 'memo'];
        $tempData = array_filter($data, function ($key) use ($ignoreFields) {
            return !in_array($key, $ignoreFields);
        }, ARRAY_FILTER_USE_KEY);

        // 字典排序 a-z
        ksort($tempData);

        // 然后将参数按照 URL 键值对的方式拼接成 `键值对字符串`
        $tempStr = urldecode(http_build_query($tempData));

        // `键值对字符串` 拼接密钥生成 `签名源字串`
        $tempStr .= $signatureKey;

        // `签名源字串` 使用 hash 256 算法计算摘要
        $digest = hash('sha256', $tempStr);

        // 对摘要 digest 做 md5 计算，md5结果转大写后，即为最终的签名
        return strtoupper(md5($digest));
    }
}
