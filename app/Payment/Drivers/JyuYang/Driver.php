<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JyuYang;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        '002' => 'CN0001', '004' => 'CN0002', '001' => 'CN0003', '003' => 'CN0004', '009' => 'CN0005',
        '006' => 'CN0006', '011' => 'CN0007', '015' => 'CN0008', '005' => 'CN0009', '012' => 'CN0010',
        '013' => 'CN0011', '007' => 'CN0012', '008' => 'CN0013', '014' => 'CN0014', '099' => 'CN0015',
        '010' => 'CN0016', '018' => 'CN0017', '033' => 'CN0018', '118' => 'CN0019', '041' => 'CN0020',
        '129' => 'CN0021', '016' => 'CN0022', '120' => 'CN0023', '020' => 'CN0024', '043' => 'CN0025',
        '122' => 'CN0026', '044' => 'CN0027', '119' => 'CN0028', '045' => 'CN0029', '046' => 'CN0030',
        '037' => 'CN0031', '047' => 'CN0032', '048' => 'CN0033', '049' => 'CN0034', '050' => 'CN0035',
        '087' => 'CN0036', '051' => 'CN0037', '164' => 'CN0038', '111' => 'CN0039', '125' => 'CN0040',
        '053' => 'CN0041', '127' => 'CN0042', '025' => 'CN0043', '054' => 'CN0044', '055' => 'CN0045',
        '056' => 'CN0046', '165' => 'CN0047', '057' => 'CN0048', '151' => 'CN0049', '175' => 'CN0050',
        '130' => 'CN0051', '058' => 'CN0052', '040' => 'CN0053', '059' => 'CN0054', '132' => 'CN0055',
        '017' => 'CN0056', '060' => 'CN0057', '061' => 'CN0058', '062' => 'CN0059', '063' => 'CN0060',
        '150' => 'CN0061', '137' => 'CN0062', '065' => 'CN0063', '066' => 'CN0064', '052' => 'CN0065',
        '068' => 'CN0066', '135' => 'CN0067', '069' => 'CN0068', '136' => 'CN0069',
        '070' => 'CN0071', '071' => 'CN0072', '064' => 'CN0073', '140' => 'CN0074', '141' => 'CN0075',
        '081' => 'CN0076', '072' => 'CN0077', '028' => 'CN0078', '034' => 'CN0079', '143' => 'CN0080',
        '073' => 'CN0081', '145' => 'CN0082', '133' => 'CN0083', '075' => 'CN0084', '244' => 'CN0085',
        '152' => 'CN0086', '077' => 'CN0087', '146' => 'CN0088', '078' => 'CN0089', '031' => 'CN0090',
        '159' => 'CN0091', '147' => 'CN0092', '097' => 'CN0093', '036' => 'CN0094', '080' => 'CN0095',
        '139' => 'CN0096', '142' => 'CN0097', '149' => 'CN0098', '082' => 'CN0099', '083' => 'CN0100',
        '084' => 'CN0101', '085' => 'CN0102', '086' => 'CN0103', '153' => 'CN0104', '123' => 'CN0105',
        '126' => 'CN0106', '257' => 'CN0107', '027' => 'CN0108', '088' => 'CN0109', '089' => 'CN0110',
        '199' => 'CN0111', '157' => 'CN0112', '090' => 'CN0113', '091' => 'CN0114', '092' => 'CN0115',
        '158' => 'CN0116', '155' => 'CN0117', '093' => 'CN0118', '094' => 'CN0119', '095' => 'CN0120',
        '079' => 'CN0122', '035' => 'CN0123', '098' => 'CN0124', '019' => 'CN0125',
        '131' => 'CN0126', '076' => 'CN0127', '148' => 'CN0128', '100' => 'CN0129', '021' => 'CN0130',
        '067' => 'CN0131', '102' => 'CN0132', '162' => 'CN0133', '103' => 'CN0134', '104' => 'CN0135',
        '105' => 'CN0136', '106' => 'CN0137', '163' => 'CN0138', '107' => 'CN0140',
        '108' => 'CN0141', '109' => 'CN0142', '166' => 'CN0143', '167' => 'CN0144', '168' => 'CN0145',
        '110' => 'CN0147', '074' => 'CN0148', '112' => 'CN0149', '169' => 'CN0150',
        '170' => 'CN0151', '113' => 'CN0152', '161' => 'CN0153', '171' => 'CN0154', '172' => 'CN0155',
        '205' => 'CN0156', '115' => 'CN0157', '173' => 'CN0158', '022' => 'CN0159',
        '116' => 'CN0161', '176' => 'CN0162', '117' => 'CN0163', '178' => 'CN0164', '134' => 'CN0165',
        '026' => 'CN0166', '029' => 'CN0167', '202' => 'CN0168', '180' => 'CN0169', '203' => 'CN0170',
        '235' => 'CN0171', '224' => 'CN0172', '192' => 'CN0173', '039' => 'CN0174', '191' => 'CN0175',
        '215' => 'CN0176', '030' => 'CN0177', '370' => 'CN0178',
        '367' => 'CN0181', '274' => 'CN0183', '497' => 'CN0185',
        '209' => 'CN0186', '537' => 'CN0187', '462' => 'CN0188', '254' => 'CN0189',
        '326' => 'CN0191', '024' => 'CN0192', '206' => 'CN0193', '193' => 'CN0194',
        '270' => 'CN0197', '222' => 'CN0198', '528' => 'CN0199', '256' => 'CN0200',
        '261' => 'CN0202', '328' => 'CN0204',
        '207' => 'CN0206', '346' => 'CN0208', '271' => 'CN0209', '478' => 'CN0210',
        '551' => 'CN0211', '194' => 'CN0212', '279' => 'CN0213', '196' => 'CN0214',
        '389' => 'CN0216',

        '372' => 'CN0010',
        '042' => 'CN0024',
        '096' => 'CN0130',
        '138' => 'CN0159',
        '128' => 'CN0043',
        '032' => 'CN0018',
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

    protected string $notifySuccessText = '0000';

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
        // 三方支付状态: 0: 已建立 1: 等待中 2: 已完成 3: 已拒绝 4: 已取消
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            0, 1 => '1',
            2 => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 0: 已建立 1: 待处理 2: 等待中 3: 已完成 4: 已拒绝 5: 已取消
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            0, 1 => '1',
            2 => '2',
            3 => '3',
            default => '4',
        };
    }

    /**
     * 通知三方回調結果
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        if ('00' != $code) {
            return response()->json(['error_code' => $this->notifyFailText, 'message' => $this->notifyFailText]);
        }

        return response()->json(['error_code' => $this->notifySuccessText, 'message' => 'Success']);
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

        // 2. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($data));

        // 3. $tempStr 拼接密鑰
        $tempStr .= '&' . $signatureKey;

        // 4. sign = $tempStr 進行 MD5 後轉為小寫
        return strtolower(md5($tempStr));
    }
}
