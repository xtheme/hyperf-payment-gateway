<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Et;

use App\Common\Response;
use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        '001' => '工商银行',
        '002' => '建设银行',
        '003' => '中国银行',
        '004' => '农业银行',
        '005' => '交通银行',
        '006' => '招商银行',
        '007' => '邮储银行',
        '008' => '中信银行',
        '009' => '民生银行',
        '010' => '浦发银行',
        '011' => '兴业银行',
        '012' => '光大银行',
        '013' => '平安银行',
        '014' => '华夏银行',
        '015' => '北京银行',
        '016' => '上海银行',
        '017' => '江苏银行',
        '018' => '广发银行',
        '019' => '宁波银行',
        '020' => '上海农村商业银行',
        '021' => '昆山农村商业银行',
        '022' => '北京农村商业银行',
        '023' => '#N/A',
        '024' => '华一银行',
        '025' => '浙江稠州商业银行',
        '026' => '厦门银行',
        '027' => '福建海峡银行',
        '028' => '南京银行',
        '029' => '福建省农村信用社联合社',
        '030' => '中原银行',
        '031' => '河南省农村信用社',
        '032' => '深圳农村商业银行',
        '033' => '深圳农村商业银行',
        '034' => '郑州银行',
        '035' => '大连银行',
        '036' => '富滇银行',
        '037' => '湖北省农村信用社联合社',
        '038' => '#N/A',
        '039' => '浙江网商银行',
        '040' => '#N/A',
        '041' => '广西北部湾银行',
        '042' => '上海农村商业银行',
        '043' => '威海市商业银行',
        '044' => '周口市商业银行',
        '045' => '库尔勒市商业银行',
        '046' => '顺德农商银行',
        '047' => '无锡农村商业银行',
        '048' => '朝阳银行',
        '049' => '浙商银行',
        '050' => '邯郸市商业银行',
        '051' => '东莞银行',
        '052' => '遵义市商业银行',
        '053' => '绍兴银行',
        '054' => '贵州省农村信用社联合社',
        '055' => '张家口市商业银行',
        '056' => '锦州银行',
        '057' => '平顶山银行',
        '058' => '汉口银行',
        '059' => '宁夏黄河农村商业银行',
        '060' => '广东南粤银行',
        '061' => '广州农村商业银行',
        '062' => '苏州银行',
        '063' => '杭州银行',
        '064' => '衡水市商业银行',
        '065' => '湖北银行',
        '066' => '嘉兴银行',
        '067' => '#N/A',
        '068' => '丹东银行',
        '069' => '安阳市商业银行',
        '070' => '恒丰银行',
        '071' => '#N/A',
        '072' => '太仓农村商业银行',
        '073' => '德阳银行',
        '074' => '宜宾市商业银行',
        '075' => '四川省农村信用合作社',
        '076' => '昆仑银行',
        '077' => '莱商银行',
        '078' => '山西尧都农村商业银行',
        '079' => '重庆三峡银行',
        '080' => '江苏省农村信用合作社联合社',
        '081' => '济宁银行',
        '082' => '晋城市商业银行',
        '083' => '阜新银行',
        '084' => '武汉农村商业银行',
        '085' => '湖北银行',
        '086' => '台州银行',
        '087' => '泰安市商业银行',
        '088' => '许昌银行',
        '089' => '宁夏银行',
        '090' => '徽商银行',
        '091' => '九江银行',
        '092' => '#N/A',
        '093' => '浙江民泰商业银行',
        '094' => '廊坊银行',
        '095' => '鞍山银行',
        '096' => '昆山农村商业银行',
        '097' => '#N/A',
        '098' => '东莞农村商业银行',
        '099' => '广州银行',
        '100' => '营口银行',
        '101' => '陕西省农村信用社联合社',
        '102' => '桂林银行',
        '103' => '青海银行',
        '104' => '成都农村商业银行',
        '105' => '青岛银行',
        '106' => '东亚银行',
        '107' => '湖北银行',
        '108' => '温州银行',
        '109' => '天津农商银行',
        '110' => '齐鲁银行',
        '111' => '广东农村信用社',
        '112' => '浙江泰隆商业银行',
        '113' => '赣州银行',
        '114' => '贵阳银行',
        '115' => '重庆银行',
        '116' => '龙江银行',
        '117' => '南充市商业银行',
        '118' => '三门峡银行',
        '119' => '江苏常熟农村商业银行',
        '120' => '吉林银行',
        '121' => '#N/A',
        '122' => '潍坊银行',
        '123' => '张家港农村商业银行',
        '124' => '浙江省农村信用社联合社',
        '125' => '兰州银行',
        '126' => '晋商银行',
        '127' => '渤海银行',
        '128' => '浙江稠州商业银行',
        '129' => '#N/A',
        '130' => '盛京银行',
        '131' => '西安银行',
        '132' => '包商银行',
        '133' => '抚顺银行',
        '134' => '邢台银行',
        '135' => '湖南省农村信用社联合社',
        '136' => '东营市商业银行',
        '137' => '鄂尔多斯银行',
        '138' => '北京农村商业银行',
        '139' => '信阳银行',
        '140' => '自贡市商业银行',
        '141' => '成都银行',
        '142' => '#N/A',
        '143' => '洛阳银行',
        '144' => '#N/A',
        '145' => '齐商银行',
        '146' => '开封市商业银行',
        '147' => '内蒙古银行',
        '148' => '重庆农村商业银行',
        '149' => '石嘴山银行',
        '150' => '德州银行',
        '151' => '上饶银行',
        '152' => '乐山市商业银行',
        '153' => '江西省农村信用社联合社',
        '154' => '晋中市商业银行',
        '155' => '#N/A',
        '156' => '#N/A',
        '157' => '#N/A',
        '158' => '江苏江阴农村商业银行',
        '159' => '云南省农村信用社联合社',
        '160' => '#N/A',
        '161' => '驻马店银行',
        '162' => '安徽省农村信用联社',
        '163' => '甘肃省农村信用社联合社',
        '164' => '#N/A',
        '165' => '#N/A',
        '166' => '乌鲁木齐市商业银行',
        '167' => '#N/A',
        '168' => '长沙银行',
        '169' => '金华银行',
        '170' => '河北银行',
        '171' => '#N/A',
        '172' => '临商银行',
        '173' => '承德银行',
        '174' => '山东省农村信用社联合社',
        '175' => '#N/A',
        '176' => '天津银行',
        '177' => '吴江农村商业银行',
        '178' => '#N/A',
        '180' => '汇丰银行',
        '181' => '渣打银行',
        '182' => '#N/A',
        '183' => '#N/A',
        '184' => '#N/A',
        '185' => '荷兰银行',
        '187' => '#N/A',
        '188' => '#N/A',
        '189' => '#N/A',
        '190' => '#N/A',
        '191' => '哈尔滨银行',
        '192' => '柳州银行',
        '193' => '东营莱商村镇银行',
        '194' => '#N/A',
        '195' => '#N/A',
        '196' => '保定市商业银行',
        '197' => '秦皇岛市商业银行',
        '198' => '#N/A',
        '199' => '广东南海农村商业银行',
        '200' => '#N/A',
        '201' => '#N/A',
        '202' => '厦门国际银行',
        '203' => '长安银行',
        '204' => '#N/A',
        '205' => '贵阳银行',
        '206' => '贵州银行',
        '207' => '辽宁省农村信用社',
        '208' => '#N/A',
        '209' => '海南银行',
        '210' => '佛山农村商业银行',
        '211' => '#N/A',
        '212' => '#N/A',
        '214' => '#N/A',
        '215' => '江西银行',
        '216' => '#N/A',
        '217' => '#N/A',
        '218' => '#N/A',
        '219' => '#N/A',
        '220' => '#N/A',
        '222' => '广东华兴银行',
        '223' => '烟台银行',
        '224' => '珠海华润银行',
        '225' => '#N/A',
        '226' => '#N/A',
        '227' => '#N/A',
        '228' => '#N/A',
        '229' => '#N/A',
        '230' => '#N/A',
        '231' => '#N/A',
        '232' => '#N/A',
        '233' => '#N/A',
        '234' => '#N/A',
        '235' => '深圳福田银座村镇银行',
        '236' => '#N/A',
        '237' => '#N/A',
        '238' => '#N/A',
        '239' => '江苏长江商业银行',
        '240' => '#N/A',
        '241' => '#N/A',
        '242' => '#N/A',
        '243' => '#N/A',
        '244' => '河北省农村信用社联合社',
        '245' => '#N/A',
        '246' => '#N/A',
        '247' => '#N/A',
        '248' => '#N/A',
        '249' => '#N/A',
        '250' => '#N/A',
        '251' => '#N/A',
        '252' => '#N/A',
        '253' => '#N/A',
        '254' => '山西省农村信用社',
        '255' => '#N/A',
        '256' => '#N/A',
        '257' => '#N/A',
        '258' => '陕西省农村信用社联合社',
        '259' => '#N/A',
        '260' => '#N/A',
        '261' => '日照银行',
        '263' => '#N/A',
        '264' => '#N/A',
        '265' => '#N/A',
        '266' => '#N/A',
        '267' => '#N/A',
        '270' => '杭州联合银行',
        '271' => '新疆农村信用社',
        '272' => '#N/A',
        '273' => '#N/A',
        '274' => '#N/A',
        '275' => '宁波通商银行',
        '277' => '#N/A',
        '278' => '江苏常熟农村商业银行',
        '279' => '沧州银行',
        '280' => '#N/A',
        '281' => '#N/A',
        '283' => '#N/A',
        '284' => '#N/A',
        '285' => '枣庄银行',
        '286' => '#N/A',
        '287' => '#N/A',
        '288' => '#N/A',
        '289' => '#N/A',
        '290' => '#N/A',
        '291' => '#N/A',
        '292' => '#N/A',
        '293' => '#N/A',
        '294' => '#N/A',
        '295' => '#N/A',
        '296' => '大同市商业银行',
        '297' => '#N/A',
        '298' => '#N/A',
        '299' => '#N/A',
        '300' => '#N/A',
        '301' => '#N/A',
        '302' => '#N/A',
        '303' => '#N/A',
        '304' => '#N/A',
        '305' => '#N/A',
        '306' => '#N/A',
        '307' => '#N/A',
        '308' => '#N/A',
        '309' => '#N/A',
        '310' => '中信百信银行',
        '311' => '#N/A',
        '312' => '#N/A',
        '313' => '#N/A',
        '314' => '乌海银行',
        '315' => '#N/A',
        '316' => '#N/A',
        '317' => '#N/A',
        '318' => '#N/A',
        '319' => '#N/A',
        '320' => '#N/A',
        '321' => '#N/A',
        '322' => '#N/A',
        '323' => '#N/A',
        '324' => '#N/A',
        '325' => '六盘水市商业银行',
        '326' => '内蒙古农村信用社',
        '327' => '#N/A',
        '328' => '天津滨海农村商业银行',
        '329' => '#N/A',
        '330' => '#N/A',
        '331' => '#N/A',
        '332' => '#N/A',
        '333' => '#N/A',
        '334' => '#N/A',
        '335' => '#N/A',
        '336' => '#N/A',
        '337' => '#N/A',
        '338' => '#N/A',
        '339' => '#N/A',
        '340' => '#N/A',
        '341' => '#N/A',
        '342' => '#N/A',
        '343' => '#N/A',
        '344' => '北京顺义银座村镇银行',
        '345' => '#N/A',
        '346' => '#N/A',
        '347' => '宁波东海银行',
        '348' => '宁波鄞州农村合作银行',
        '349' => '#N/A',
        '350' => '#N/A',
        '351' => '#N/A',
        '352' => '#N/A',
        '353' => '#N/A',
        '354' => '#N/A',
        '355' => '#N/A',
        '356' => '#N/A',
        '357' => '#N/A',
        '358' => '甘肃银行',
        '359' => '#N/A',
        '360' => '#N/A',
        '361' => '#N/A',
        '362' => '#N/A',
        '363' => '长沙农商银行',
        '364' => '长治银行',
        '365' => '#N/A',
        '366' => '#N/A',
        '367' => '#N/A',
        '368' => '#N/A',
        '369' => '#N/A',
        '370' => '#N/A',
        '371' => '#N/A',
        '372' => '光大银行',
        '373' => '#N/A',
        '374' => '#N/A',
        '375' => '#N/A',
        '376' => '#N/A',
        '377' => '#N/A',
        '378' => '#N/A',
        '379' => '#N/A',
        '380' => '#N/A',
        '381' => '#N/A',
        '382' => '#N/A',
        '383' => '#N/A',
        '384' => '#N/A',
        '385' => '#N/A',
        '386' => '#N/A',
        '387' => '曲靖市商业银行',
        '388' => '#N/A',
        '389' => '江西赣州银座村镇银行',
        '390' => '#N/A',
        '391' => '#N/A',
        '392' => '江苏射阳农村商业银行',
        '393' => '#N/A',
        '394' => '#N/A',
        '395' => '#N/A',
        '396' => '#N/A',
        '397' => '#N/A',
        '398' => '#N/A',
        '399' => '西藏银行',
        '400' => '#N/A',
        '401' => '#N/A',
        '402' => '#N/A',
        '403' => '#N/A',
        '404' => '辽阳银行',
        '405' => '#N/A',
        '406' => '余杭农村商业银行',
        '407' => '#N/A',
        '408' => '#N/A',
        '409' => '#N/A',
        '410' => '#N/A',
        '411' => '#N/A',
        '412' => '达州银行股份有限公司',
        '413' => '#N/A',
        '414' => '#N/A',
        '415' => '阳泉市商业银行',
        '416' => '#N/A',
        '417' => '#N/A',
        '418' => '#N/A',
        '419' => '#N/A',
        '420' => '#N/A',
        '421' => '#N/A',
        '422' => '昆明市农村信用合作社联合社',
        '423' => '#N/A',
        '424' => '#N/A',
        '425' => '#N/A',
        '426' => '#N/A',
        '427' => '#N/A',
        '428' => '#N/A',
        '429' => '#N/A',
        '430' => '#N/A',
        '431' => '#N/A',
        '432' => '#N/A',
        '433' => '#N/A',
        '434' => '#N/A',
        '435' => '#N/A',
        '436' => '#N/A',
        '437' => '#N/A',
        '438' => '#N/A',
        '439' => '#N/A',
        '440' => '#N/A',
        '441' => '邯郸市商业银行',
        '442' => '#N/A',
        '443' => '#N/A',
        '444' => '#N/A',
        '445' => '#N/A',
        '446' => '#N/A',
        '447' => '#N/A',
        '448' => '#N/A',
        '449' => '#N/A',
        '450' => '南洋商业银行',
        '451' => '#N/A',
        '452' => '广东南海农村商业银行',
        '453' => '#N/A',
        '454' => '哈密市商业银行',
        '455' => '#N/A',
        '456' => '#N/A',
        '457' => '蓝海银行',
        '458' => '#N/A',
        '459' => '#N/A',
        '460' => '#N/A',
        '461' => '#N/A',
        '462' => '泉州银行',
        '463' => '#N/A',
        '464' => '#N/A',
        '465' => '#N/A',
        '466' => '#N/A',
        '467' => '#N/A',
        '468' => '#N/A',
        '469' => '#N/A',
        '470' => '#N/A',
        '471' => '#N/A',
        '472' => '#N/A',
        '473' => '#N/A',
        '474' => '#N/A',
        '475' => '顺德农村商业银行',
        '476' => '#N/A',
        '477' => '凉山州商业银行',
        '478' => '唐山市商业银行',
        '479' => '#N/A',
        '480' => '#N/A',
        '481' => '#N/A',
        '482' => '#N/A',
        '483' => '#N/A',
        '484' => '#N/A',
        '485' => '浙江三门银座村镇银行',
        '486' => '#N/A',
        '487' => '#N/A',
        '488' => '#N/A',
        '489' => '#N/A',
        '490' => '#N/A',
        '491' => '#N/A',
        '492' => '#N/A',
        '493' => '#N/A',
        '494' => '#N/A',
        '495' => '#N/A',
        '496' => '#N/A',
        '497' => '海南省农村信用社联合社',
        '498' => '#N/A',
        '499' => '珠海农商银行',
        '500' => '#N/A',
        '501' => '#N/A',
        '502' => '#N/A',
        '503' => '#N/A',
        '504' => '#N/A',
        '505' => '#N/A',
        '506' => '商丘市商业银行',
        '507' => '#N/A',
        '508' => '#N/A',
        '509' => '#N/A',
        '510' => '#N/A',
        '511' => '梅州客商银行',
        '512' => '#N/A',
        '513' => '#N/A',
        '514' => '#N/A',
        '515' => '#N/A',
        '516' => '#N/A',
        '517' => '深圳前海微众银行',
        '518' => '#N/A',
        '519' => '绵阳市商业银行',
        '520' => '#N/A',
        '521' => '#N/A',
        '522' => '#N/A',
        '523' => '#N/A',
        '524' => '#N/A',
        '525' => '#N/A',
        '526' => '#N/A',
        '527' => '湖州银行',
        '528' => '#N/A',
        '529' => '#N/A',
        '530' => '#N/A',
        '531' => '#N/A',
        '532' => '#N/A',
        '533' => '焦作市商业银行',
        '534' => '#N/A',
        '535' => '紫金农商银行',
        '536' => '浙江萧山农村商业银行',
        '537' => '雅安市商业银行',
        '538' => '#N/A',
        '539' => '#N/A',
        '540' => '#N/A',
        '541' => '#N/A',
        '542' => '#N/A',
        '543' => '#N/A',
        '544' => '#N/A',
        '545' => '新疆汇和银行',
        '546' => '新疆银行',
        '547' => '#N/A',
        '548' => '#N/A',
        '549' => '#N/A',
        '550' => '葫芦岛银行',
        '551' => '#N/A',
        '552' => '#N/A',
        '553' => '漯河市商业银行',
        '554' => '#N/A',
        '555' => '#N/A',
        '556' => '#N/A',
        '557' => '#N/A',
        '558' => '#N/A',
        '559' => '#N/A',
        '560' => '#N/A',
        '561' => '#N/A',
        '562' => '#N/A',
        '563' => '#N/A',
        '564' => '鞍山银行',
        '565' => '#N/A',
        '566' => '鹤壁银行',
        '567' => '#N/A',
        '568' => '#N/A',
        '569' => '#N/A',
        '570' => '#N/A',
        '571' => '#N/A',
        '572' => '#N/A',
        '573' => '#N/A',
        '574' => '#N/A',
        '575' => '#N/A',
        '576' => '#N/A',
        '577' => '攀枝花市商业银行',
        '578' => '#N/A',
        '999' => '#N/A',
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true; // 分=false 元=true

    protected string $signField = 'pay_md5_sign';

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
     * 代收: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        // 三方支付状态: 0=未處理, 1=成功，未返回, 2=成功，已返回, 3=失敗，逾期失效, 4=失敗，金額不符, 5=訂單異常
        // 集成订单状态: 0=失败, 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            0 => '1',
            '30000', 1, 2 => '2',
            3 => '5',
            4 => '4',
            default => '0',
        };
    }

    /**
     * 代付: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 0=未處理, 1=處理中, 2=已出款, 3=已駁回, 4=核實不成功, 5=餘額不足
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中

        return match ($status) {
            0, 1, => '2',
            2, '30000' => '3',
            default => '5',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

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
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        if (isset($data['sign'])) {
            unset($data['sign']);
        }

        // 1. 字典排序
        ksort($data);

        // 2. 排除空值欄位參與簽名
        $data = array_filter($data);

        // 3. $tempData 轉成字串
        $tempStr = http_build_query($data, '', '&', PHP_QUERY_RFC3986);

        // 4. 反轉譯字串
        $tempStr = urldecode($tempStr);

        // 5. $tempStr 拼接密鑰
        $tempStr .= '&key=' . $signatureKey;

        // 6. MD5 並轉大寫
        return strtoupper(md5($tempStr));
    }
}
