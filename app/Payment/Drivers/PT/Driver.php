<?php

declare(strict_types=1);

namespace App\Payment\Drivers\PT;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        '001' => '1', '004' => '2', '003' => '3', '002' => '4', '005' => '5',
        '008' => '6', '012' => '7', '014' => '8', '009' => '9', '018' => '10',
        '013' => '11', '006' => '12', '011' => '13', '010' => '14', '015' => '15',
        '007' => '16', '515' => '17', '127' => '18', '106' => '19', '019' => '20',
        '138' => '21', '028' => '22', '049' => '23', '016' => '24', '042' => '25',
        '063' => '26', '128' => '27', '058' => '30',
        '168' => '33', '305' => '34',
        '070' => '37', '090' => '39', '410' => '40',
        '079' => '41', '529' => '43', '033' => '45',
        '538' => '48', '181' => '49',
        '182' => '51', '180' => '52', '026' => '53', '120' => '54', '099' => '55',
        '035' => '56', '176' => '57', '034' => '58', '089' => '59', '115' => '60',
        '205' => '61', '017' => '63', '065' => '64', '275' => '65',
        '206' => '66', '105' => '67', '125' => '68', '141' => '69', '450' => '70',
        '170' => '71', '241' => '72', '108' => '73', '036' => '74',
        '556' => '76', '027' => '78',
        '040' => '81', '041' => '82', '061' => '83', '111' => '85',
        '215' => '86', '192' => '87', '497' => '88', '051' => '89',
        '130' => '91', '254' => '92', '391' => '93', '098' => '94', '086' => '95',
        '191' => '96', '093' => '99', '075' => '100',
        '031' => '101', '037' => '102', '174' => '103', '054' => '104', '378' => '105',
        '159' => '107', '326' => '108', '132' => '109',
        '162' => '111', '261' => '114', '393' => '115',
        '201' => '116', '153' => '117', '214' => '118', '081' => '120',
        '148' => '121', '110' => '122', '237' => '125',
        '224' => '126', '102' => '127', '053' => '128', '263' => '130',

        '372' => '7',
        '144' => '10',
        '286' => '130',
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

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
        // todo 请依据三方逻辑订制状态转换规则
        // (范本) 三方支付状态: 0=订单生成, 1=支付中, 2=支付成功, 3=业务处理完成
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '1' => '1',
            '2', '3' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 10:建单, 30,31,20: 处理中, 33:申请结果待确认, 21,41:成功, 51:提现结果待确认, 22,32,52:失败并退还金额
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            10 => '0',
            30, 31, 20 => '1',
            33, 51 => '6',
            21, 41 => '3',
            default => '5',
        };
    }

    /**
     * 转换三方行銀代碼
     */
    public function transformWithdrawBankCode($code): string
    {
        return match ($code) {
            '001' => '1',
            '004' => '2',
            '003' => '3',
            '002' => '4',
            '005' => '5',
            '008' => '6',
            '012', '372' => '7',
            '014' => '8',
            '009' => '9',
            '018', '144' => '10',
            '013' => '11',
            '006' => '12',
            '011' => '13',
            '010' => '14',
            '015' => '15',
            '007' => '16',
            '515' => '17',
            '127' => '18',
            '106' => '19',
            '019' => '20',
            '138' => '21',
            '028' => '22',
            '049' => '23',
            '016' => '24',
            '042' => '25',
            '063' => '26',
            '128' => '27',
            '058' => '30',
            '168' => '33',
            '305' => '34',
            '070' => '37',
            '090' => '39',
            '410' => '40',
            '079' => '41',
            '529' => '43',
            '033' => '45',
            '538' => '48',
            '181' => '49',
            '182' => '51',
            '180' => '52',
            '026' => '53',
            '120' => '54',
            '099' => '55',
            '035' => '56',
            '176' => '57',
            '034' => '58',
            '089' => '59',
            '115' => '60',
            '205' => '61',
            '017' => '63',
            '065' => '64',
            '275' => '65',
            '206' => '66',
            '105' => '67',
            '125' => '68',
            '141' => '69',
            '450' => '70',
            '170' => '71',
            '241' => '72',
            '108' => '73',
            '036' => '74',
            '556' => '76',
            '027' => '78',
            '040' => '81',
            '041' => '82',
            '061' => '83',
            '111' => '85',
            '215' => '86',
            '192' => '87',
            '497' => '88',
            '051' => '89',
            '130' => '91',
            '254' => '92',
            '391' => '93',
            '098' => '94',
            '086' => '95',
            '191' => '96',
            '093' => '99',
            '075' => '100',
            '031' => '101',
            '037' => '102',
            '174' => '103',
            '054' => '104',
            '378' => '105',
            '159' => '107',
            '326' => '108',
            '132' => '109',
            '162' => '111',
            '261' => '114',
            '393' => '115',
            '201' => '116',
            '153' => '117',
            '214' => '118',
            '081' => '120',
            '148' => '121',
            '110' => '122',
            '237' => '125',
            '224' => '126',
            '102' => '127',
            '053' => '128',
            '263', '286' => '130',

            default => $code,
        };
    }

    /**
     * 通知三方回調結果
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        $re = intval($this->notifySuccessText);

        if ('00' != $code) {
            $re = intval($this->notifyFailText);
        }

        return response()->json(['Success' => $re]);
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

        // 1. 欄位參與簽名
        $tempStr = '';

        foreach ($data as $key => $value) {
            if ('status' != $key) {
                if ('' != $value) {
                    $tempStr .= $value . '&';
                } else {
                    $tempStr .= '&';
                }
            } else {
                if (1 == $value) {
                    $tempStr .= 'true&';
                } else {
                    $tempStr .= 'false&';
                }
            }
        }
        $tempStr = substr($tempStr, 0, strlen($tempStr) - 1);

        // 2. $tempStr 拼接密鑰
        $tempStr .= '&' . $signatureKey;

        // 3. sign = $tempStr 進行 MD5 後轉為小寫
        return strtolower(base64_encode(md5(mb_convert_encoding($tempStr, 'UTF-8'), true)));
    }
}
