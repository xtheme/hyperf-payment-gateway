<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShunSin;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        '001' => 20, '002' => 11, '003' => 10, '004' => 3, '005' => 16,
        '006' => 17, '007' => 2, '008' => 19, '009' => 12, '010' => 7,
        '011' => 22, '012' => 8, '013' => 14, '014' => 13, '015' => 23,
        '016' => 18, '017' => 299, '018' => 4, '019' => 15, '020' => 9,
        '021' => 110, '022' => 1,
        '026' => 278, '027' => 202, '028' => 303, '029' => 121, '030' => 260,
        '031' => 127, '033' => 301, '034' => 290, '035' => 193,
        '036' => 219, '039' => 157, '040' => 125,
        '041' => 151, '042' => 305, '043' => 273, '045' => 329,
        '046' => 114, '049' => 28, '050' => 264,
        '051' => 158, '053' => 200, '054' => 122,
        '056' => 251, '057' => 209, '058' => 147, '059' => 112, '060' => 213,
        '061' => 106, '062' => 266, '063' => 6, '065' => 145,
        '066' => 198, '067' => 146, '068' => 169, '070' => 184,
        '074' => 259, '075' => 137,
        '076' => 252, '077' => 207, '078' => 113, '079' => 262,
        '081' => 27, '082' => 280, '083' => 172, '084' => 117,
        '086' => 142, '089' => 254, '090' => 29,
        '091' => 205, '093' => 243, '094' => 164, '095' => 332,
        '096' => 110, '098' => 105, '099' => 153, '100' => 180,

        '101' => 174, '102' => 143, '103' => 224, '104' => 102, '105' => 25,
        '106' => 306, '108' => 300, '109' => 115, '110' => 206,
        '111' => 124, '112' => 155, '113' => 244, '115' => 154,
        '116' => 194, '120' => 298,
        '122' => 331, '123' => 118, '124' => 141, '125' => 221,
        '126' => 26, '127' => 30, '128' => 201, '130' => 269,
        '131' => 220, '132' => 256, '133' => 170, '134' => 187, '135' => 126,
        '137' => 191, '138' => 294,
        '141' => 5, '142' => 326, '143' => 210, '145' => 167,
        '147' => 190, '148' => 103, '149' => 267, '150' => 307,
        '151' => 161, '153' => 132,
        '158' => 109, '159' => 140,
        '162' => 120, '163' => 123, '165' => 131,
        '168' => 276, '169' => 199, '170' => 282,
        '171' => 175, '172' => 171, '173' => 317, '174' => 138,
        '176' => 149, '177' => 116, '180' => 342,
        '182' => 333,
        '189' => 313,
        '191' => 287, '192' => 152, '194' => 217,
        '196' => 283,

        '201' => 293, '202' => 204, '203' => 275, '205' => 285, '206' => 150,
        '207' => 133, '208' => 163, '209' => 215, '215' => 271, '222' => 31,
        '223' => 24, '224' => 214, '225' => 130, '235' => 315, '244' => 128,
        '247' => 228, '254' => 136, '258' => 183, '261' => 208, '263' => 156,
        '264' => 181, '265' => 250, '270' => 32, '271' => 139, '275' => 195,
        '278' => 101, '279' => 185, '286' => 165, '296' => 189, '298' => 104,
        '314' => 192, '316' => 223, '318' => 265, '326' => 134, '343' => 235,
        '344' => 335, '346' => 309, '347' => 196, '348' => 323, '358' => 263,
        '364' => 258, '365' => 177, '372' => 8, '374' => 144, '376' => 227,
        '383' => 236, '387' => 176, '389' => 248, '391' => 255, '393' => 108,
        '395' => 218, '399' => 225, '402' => 119, '412' => 216, '431' => 179,
        '446' => 135, '452' => 111, '462' => 203, '473' => 230, '478' => 186,
        '483' => 336, '484' => 166, '485' => 316, '491' => 289, '494' => 107,
        '497' => 129, '506' => 312, '511' => 238, '515' => 341, '519' => 308,
        '526' => 148, '527' => 197, '528' => 232, '533' => 211, '537' => 325,
        '546' => 222, '547' => 162, '550' => 178, '564' => 332, '999' => 999,
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

    protected string $notifySuccessText = 'OK';

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
        // 三方支付状态: 成功支付的订单 ErrorCode=00, 未成功支付的订单示例 "ErrorCode":"32000"
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '32000' => '1',
            '00' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 1=等待处理, 2=准备打款, 3=已打款, 4=已拒绝
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            '1', '2' => '1',
            '3' => '3',
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
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($data));

        // 2. $tempStr 拼接密鑰
        $tempStr .= '' . $signatureKey;

        // 3. sign = $tempStr 進行 MD5 後轉為小寫
        return strtolower(md5($tempStr));
    }
}
