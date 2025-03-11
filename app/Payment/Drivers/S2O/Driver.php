<?php

declare(strict_types=1);

namespace App\Payment\Drivers\S2O;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        '001' => 'ICBKCNBJ',
        '002' => 'PCBCCNBJ',
        '003' => 'BKCHCNBJ',
        '004' => 'ABOCCNBJ',
        '005' => 'COMMCNSH',
        '006' => 'CMBCCNBS',
        '007' => 'PSBCCNBJ',
        '008' => 'CIBKCNBJ',
        '009' => 'MSBCCNBJ',
        '010' => 'SPDBCNSH',
        '011' => 'FJIBCNBA',
        '012' => 'EVERCNBJ',
        '013' => 'SZDBCNBS',
        '014' => 'HXBKCNBJ',
        '015' => 'BJCNCNBJ',
        '016' => 'BOSHCNSH',
        '017' => 'BOJSCNBN',
        '018' => 'GDBKCN22',
        '019' => 'BKNBCN2N',
        '020' => 'SHRCCNSH',
        '021' => 'KSRCB',
        '022' => 'BJRCB',
        '023' => 'HVBKCNBJ',
        '024' => 'FSBCCNSH',
        '025' => 'CZCBCN2X',
        '026' => 'CBXMCNBA',
        '027' => 'FZCBCNBS',
        '028' => 'NJCBCNBN',
        '029' => 'FJRCU',
        '030' => 'ZHONCNBJ',
        '031' => 'HNRCU',
        '032' => 'SRCCCNBS',
        '033' => 'SRCCCNBS',
        '034' => 'ZZBKCNBZ',
        '035' => 'DLCBCNBD',
        '036' => 'KCCBCN2K',
        '037' => 'HBRCC',
        '039' => 'ZJMYBANK',
        '040' => 'GX966888',
        '041' => 'BGBKCNBJ',
        '042' => 'SHRCCNSH',
        '043' => 'WHBKCNBJ',
        '046' => 'RCCSCNBS',
        '047' => 'WRCB',
        '049' => 'ZJCBCN2N',
        '051' => 'DGCBCN22',
        '053' => 'SXCBCN2X',
        '054' => 'GZRCU',
        '055' => 'ZJKCCB',
        '056' => 'JZCBCNBJ',
        '057' => 'PDSBANK',
        '058' => 'WHCBCNBN',
        '060' => 'GDPBCN22',
        '061' => 'GZRCBK',
        '062' => 'DWRBCNSU',
        '063' => 'HZCBCN2H',
        '064' => 'HSBANK',
        '065' => 'HBBKCNBN',
        '066' => 'BOJXCNBJ',
        '067' => 'HRXJCNBC',
        '068' => 'DANDONGBANK',
        '070' => 'HFBACNSD',
        '076' => 'CKLBCNBJ',
        '077' => 'LWCBCNBJ',
        '079' => 'CCQTGB',
        '080' => 'JSRCU',
        '081' => 'BKJNCNBJ',
        '082' => 'JCBANK',
        '083' => 'FXBKCNBJ',
        '085' => 'HBBKCNBN',
        '086' => 'TZBKCNBT',
        '087' => 'TACCB',
        '089' => 'YCCBCNBY',
        '090' => 'HFCBCNSH',
        '091' => 'BOJJCNBJ',
        '093' => 'ZJMTCNSH',
        '094' => 'BOLFCNBL',
        '096' => 'KSRCB',
        '097' => 'YNHTBANK',
        '099' => 'GZCBCN22',
        '100' => 'YKCBCNBJ',
        '101' => 'SXRCU',
        '102' => 'GLBKCNBG',
        '103' => 'BOXNCNBL',
        '104' => 'CDRCB',
        '105' => 'QCCBCNBQ',
        '106' => 'BEASCNSH',
        '107' => 'HBBKCNBN',
        '108' => 'WZCBCNSH',
        '109' => 'TRCBANK',
        '110' => 'JNSHCNBN',
        '111' => 'GDRCU',
        '112' => 'ZJTLCNBH',
        '113' => 'BKGZCNBN',
        '114' => 'GYCBCNSI',
        '115' => 'CQCBCN22',
        '116' => 'LJBCCNBH',
        '117' => 'NCCCCNBD',
        '119' => 'CSCBCNSH',
        '120' => 'JLBKCNBJ',
        '122' => 'WFCBCNBN',
        '123' => 'ZRCBANK',
        '124' => 'ZJ96596',
        '125' => 'LZCBCNBL',
        '126' => 'JSHBCNBJ',
        '127' => 'CHBHCNBT',
        '128' => 'CZCBCN2X',
        '130' => 'SYCBCNBY',
        '131' => 'IXABCNBX',
        '132' => 'BTCBCNBJ',
        '134' => 'XTBANK',
        '136' => 'DYLSBANK',
        '137' => 'ORDOSBANK',
        '138' => 'BJRCB',
        '140' => 'ZGBANK',
        '141' => 'CBOCCNBC',
        '142' => 'HNBNCNBJ',
        '143' => 'BOLYCNB1',
        '144' => 'GDBKCN22',
        '145' => 'ZBBKCNBZ',
        '147' => 'HSSYCNBH',
        '148' => 'CQRCB',
        '150' => 'DECLCNBJ',
        '151' => 'BKOSR',
        '152' => 'LSCCB',
        '153' => 'JXRCU',
        '159' => 'YNRCC',
        '160' => 'GX966888',
        '162' => 'AHRCU',
        '163' => 'GSRCU',
        '165' => 'JLPRCB',
        '166' => 'UCCBCNBJ',
        '168' => 'CHCCCNSS',
        '169' => 'JHCBCNBJ',
        '170' => 'BKSHCNBJ',
        '171' => 'YZBANK',
        '172' => 'LYCBCNBL',
        '173' => 'CHENGDEBANK',
        '174' => 'SDRCU',
        '175' => 'NCCKCNBN',
        '176' => 'TCCBCNBT',
        '180' => 'HSBCCN',
        '191' => 'HCCBCNBH',
        '192' => 'LZBKCNBJ',
        '193' => 'DYLSBANK',
        '194' => 'TFB',
        '196' => 'BDB',
        '199' => 'NHRC',
        '202' => 'IBXHCNBA',
        '203' => 'CABZCNB1',
        '205' => 'GYCBCNSI',
        '206' => 'GZB',
        '209' => 'BANKOFHAINAN',
        '215' => 'JXB',
        '219' => 'DRCBANK',
        '222' => 'GDHBCN22',
        '224' => 'CRBANK',
        '229' => 'JLBKCNBJ',
        '235' => 'FTYZB',
        '242' => 'SHRCCNSH',
        '244' => 'HEBRCU',
        '261' => 'RZCBCNBD',
        '263' => 'WJRCB',
        '271' => 'XJRC',
        '275' => 'BINHCN2N',
        '278' => 'CSCBCNSH',
        '279' => 'CZCCB',
        '315' => 'UCCBCNBJ',
        '316' => 'UCCBCNBJ',
        '326' => 'MGRC',
        '331' => 'TCRCB',
        '358' => 'GSB',
        '372' => 'EVERCNBJ',
        '378' => 'JLPRCB',
        '387' => 'QJCCB',
        '389' => 'GZYZB',
        '390' => 'JYRCB',
        '441' => 'BKHDCNB1',
        '452' => 'NHRC',
        '462' => 'BKQZCNBZ',
        '478' => 'TSBANK',
        '497' => 'HAINANBANK',
        '517' => 'WEBANK',
        '559' => 'MGC',
    ];

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
     * ============================================
     *  商戶接口
     * ============================================
     */

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
        // 三方支付状态:  success=交易成功, pending=待处理, delay=交易逾时, cancel=取消交易, fail=交易失败

        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'success' => '2',
            'pending' => '1',
            'delay' => '5',
            'cancel' => '4',
            'fail' => '4',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态:  success=交易成功, pending=待处理, cancel=取消交易, fail=交易失败
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            'success' => '3',
            'pending' => '1',
            'cancel' => '4',
            'fail' => '5',
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

        // 收到回調後，請回傳 OK
        return response()->raw($this->notifySuccessText);
    }

    public function transformPaymentError($code): string
    {
        $code = (string) $code;

        return match ($code) {
            '10001' => '加簽異常',
            '10002' => '代理未授權',
            '10003' => '支付管道異常',
            '1' => '成功',
            '-1' => '建单异常',
            '-2' => '商户订单号存在',
            '-3' => '商户订单号格式异常',
            '-4' => '金额异常',
            '-5' => '持有人银行名称必填',
            '-6' => '持有人银行代码必填',
            '-7' => '持有人银行帐号必填',
            '-8' => '付款人姓名必填',
            '-9' => '缴费内容格式异常',
            '-10' => '类型异常',
            '-11' => '缴费超商代码异常',
            '-12' => '同单多组银行资料异常',
            '-13' => '付款人电话必填',
            default => '未知错误',
        };
    }

    public function transformWithdrawError($code): string
    {
        $code = (string) $code;

        return match ($code) {
            '10001' => '加簽異常',
            '10002' => '代理未授權',
            '10003' => '支付管道異常',
            '1' => '成功',
            '-1' => '建单异常',
            '-2' => '商户订单号存在',
            '-3' => '商户订单号格式异常',
            '-4' => '金额异常',
            '-5' => '持有人银行名称必填',
            '-6' => '持有人银行代码必填',
            '-7' => '持有人银行帐号必填',
            '-8' => '商户余额不足',
            default => '未知错误',
        };
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

        // 1. 字典排序
        ksort($data);

        // 2. 排除空值欄位參與簽名
        $data = array_filter($data);

        // 2. $tempData 轉成字串
        $tempStr = http_build_query($data);

        // 3. $tempStr 拼接密鑰
        $tempStr .= '&key=' . $signatureKey;

        // 4. MD5
        return md5($tempStr);
    }
}
