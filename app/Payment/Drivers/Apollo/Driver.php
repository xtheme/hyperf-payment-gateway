<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Apollo;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        'ID0001' => 'dp_BCA_ID',
        'ID0002' => 'dp_BNI_ID',
        'ID0003' => 'dp_BRI_ID',
        'ID0004' => 'dp_CIMB_ID',
        'ID0005' => 'dp_DANA_ID',
        'ID0006' => 'dp_LINKAJA_ID',
        'ID0007' => 'dp_MANDIRI_ID',
        'ID0008' => 'dp_OCBC_ID',
        'ID0009' => 'dp_OVO_ID',
        'ID0010' => 'dp_TELKOMSEL_ID',
        'ID0011' => 'dp_XL_ID',
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

    protected string $notifySuccessText = 'SUCCESS”';

    protected string $notifyFailText = 'FAIL';

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

    /**
     * 三方回調通知, 更新訂單
     */
    public function withdrawNotify(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawNotify::class, ['config' => $this->config])->request($request);
    }

    /**
     * 查詢訂單(交易), 返回訂單明細
     */
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
        $status = intval($status);

        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: -2：上分失败, -1：建單失敗, 0：处理中, 1：上分成功, 3：无须上分, 9：API审核
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            0, 9 => '1',
            1 => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        $status = intval($status);

        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: -2：建單失敗, -1：出款失败, 0：处理中, 1：出款成功, 8：API审核, 9：后台审核
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            0 => '1',
            1 => '3',
            8, 9 => '6',
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
            return response()->raw($this->notifyFailText);
        }

        return response()->raw($this->notifySuccessText);
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

    /**
     * 代付簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        $tempStr = '';

        foreach ($data as $key => $value) {
            if ('' != $value) {
                $tempStr .= $value;
            }
        }
        $tempStr .= $signatureKey;

        return strtolower(md5($tempStr));

        /*
        // todo 以下为范本, 请依据三方签名逻辑订制
        // 1. 字典排序
        ksort($data);

        // 2. 排除空字串欄位參與簽名
        $tempData = array_filter($data, fn($value) => $value !== '', ARRAY_FILTER_USE_BOTH);

        // 3. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($tempData));

        // 4. $tempStr 拼接密鑰
        $tempStr .= '&key=' . $signatureKey;

        // 5. sign = $tempStr 進行 MD5 後轉為大寫
        return strtoupper(md5($tempStr));
        */
    }

    /**
     * 代收簽名規則
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }
}
