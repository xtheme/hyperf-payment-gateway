<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZhuanQianBao;

use App\Common\Response;
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
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockQuery(string $orderNo): ResponseInterface
    {
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 1=新訂單, 2=待確認, 3=已成功, 4=已失敗, 5=已取消
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '1', '2' => '1',
            '3' => '2',
            default => '4',
        };
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
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockWithdrawQuery(string $orderNo): ResponseInterface
    {
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * 代付: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 1=訂單成立, 2=待確認, 3=支付成功, 4=充值失敗, 5=取消充值, 6=已搶單
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            // '-2'     => '5',
            '1', '2', '6' => '1',
            '3' => '3',
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
            return response()->raw($this->notifyFailText);
        }

        return response()->raw($this->notifySuccessText);
    }

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        // 参数名ASCII码从小到大排序（字典序）；
        // 如果参数的值为空不参与签名；
        // 参数名区分大小写；
        // 验证调用返回或支付中心主动通知签名时，传送的sign参数不参与签名，将生成的签名与该sign值作校验。
        // 支付中心接口可能增加字段，验证签名时必须支持增加的扩展字段

        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

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

        // 5. sign = $tempStr 進行 MD5 後轉為大寫
        return strtolower(md5($tempStr));
    }
}
