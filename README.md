# Payment Gateway

以下將以 ```PG``` 簡稱 Payment Gateway, 以 ```IG``` 簡稱集成網關 (Integrated Gateway).

```PG``` 的目的是在 ```IG``` 與三方之間作為代收代付的中間層, 並提供統一的接口, 以及統一的錯誤處理, 當前最新版本為 ```v1```.

```PG``` 本身不配置保存三方金流提供給商戶的相關配置, 例如: 商戶號, 商戶密鑰, 三方金流的支付渠道等等,
這些資訊需透過 ```IG``` 請求參數取得, 但 ```PG``` 會將第一次獲取的創建訂單資訊暫存在 Redis 中. 其目的主要有兩個:

1. 避免查詢訂單是否存在需要去 DB 查詢或由 ```IG``` 取得, 降低 ```IG``` 的壓力.
2. 減少與 ```IG``` 的溝通, 創建訂單時請求的參數中需要包含三方查詢訂單接口地址, 當三方回調 ```PG``` 可發起查詢訂單進行二次校驗.

請求流程：```商戶 (前端站點) <=> IG <=> PG <=> 3rd-party```

## 快速開始

- [安裝需求](#安裝需求)
- [部署流程](#部署流程)
- [目錄結構](#目錄結構)
- [狀態碼規範](#狀態碼規範)
- [代收接口](#代收接口)
- [代付接口](#代付接口)
- [創建新渠道](#創建新渠道)
- [渠道配置](#渠道配置)

## 安裝需求

- PHP >= 8.0
- PHP Swoole extension >= 5.0
    - swoole.use_shortname=Off
- Redis

## 部署流程

1. git clone https://gitlab.com/yc-project/payment-gateway.git
2. cd payment-gateway && composer install
3. cp .env.example .env
4. 編輯 .env 配置 APP_HOST / Redis
5. 本項目不使用 MySQL, 請忽略 MySQL 相關配置
6. 啟動 HTTP 服務 ```php bin/hyperf.php start```
7. 訪問 http://127.0.0.1:9501 查看是否顯示 json 格式成功信息

## 目錄結構

- app
    - Console
        - Commands
            - PaymentCommand.php // 生成支付代碼命令
    - Exceptions
        - Handler
            - ApiExceptionHandler.php // 錯誤處理後採 json 拋出
        - ApiException.php
    - Http
        - Controllers
            - Api
                - PaymentController.php // 代收控制器
                - WithdrawController.php // 代付控制器
        - Middleware
        - Requests
    - Payment
        - Contracts // 支付渠道驅動介面 (方法約束)
        - Driver
            - xxx // 每個支付渠道代碼集成在一個目錄下
                - Driver.php // 支付渠道驅動, 設定配置以及公用方法如製作簽名
                - MockNotify.php // 模擬三方回調```代收```訂單
                - MockQuery.php // 模擬 IG 查詢```代收```訂單
                - MockWithdrawNotify.php // 模擬三方回調```代付```訂單
                - MockWithdrawQuery.php // 模擬 IG 查詢```代付```訂單
                - OrderCreate.php // 創建```代收```訂單
                - OrderNotify.php // 三方回調```代收```訂單
                - OrderQuery.php // 查詢```代收```訂單
                - WithdrawCreate.php // 創建```代付```訂單
                - WithdrawNotify.php // 三方回調```代付```訂單
                - WithdrawQuery.php // 查詢```代付```訂單
            - AbstractDriver.php // 支付渠道驅動抽象類 (公用方法)
            - PaymentGateway.php // 調用支付渠道驅動
- config
    - autoload
        - payment.php // 配置支付渠道的時區與請求方式
    - routes.php // 路由配置

## 狀態碼規範

### 代收狀態

    1=待付款
    2=已完成
    3=金額錯誤
    4=交易失敗
    5=逾期失效

### 代付狀態

    0=預約中
    1=待處理
    2=執行中
    3=成功
    4=取消
    5=失敗
    6=審核中

## 代收接口

### 創建訂單

- 文件位置: app/Payment/Driver/`{渠道代碼}`/OrderCreate.php
- 請求方式:
    - POST
    - content-type: application/json
- 請求地址: /api/v1/payment/create
- 請求參數:

```json
{
  "site_id": "商戶代號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "endpoint_url": "三方網關創建訂單地址",
  "query_url": "三方網關查詢訂單地址",
  "callback_url": "三方回調時轉發通知 IG 的回調地址",
  "merchant_id": "商戶號",
  "merchant_key": "商戶密鑰",
  "order_id": "訂單號",
  "amount": "存款金額, 單位為分",
  "currency": "幣別, 預設 CNY",
  "bank_name": "銀行名稱",
  "bank_code": "銀行代碼",
  "bank_branch_name": "銀行分行名稱",
  "bank_branch_code": "銀行分行代號",
  "bank_account": "銀行帳號 / 電子錢包 / 支付寶賬號",
  "user_name": "用戶名 / 取款人",
  "user_phone": "用戶手機號",
  "user_id": "用戶ID",
  "header_params": "額外的請求頭參數",
  "body_params": "額外的請求參數"
}
```

- 返回結果:

```json
{
  "order_no": "商戶订单号",
  "trade_no": "三方交易号",
  "link": "支付网址",
  "cashier_link": "PG收银台网址"
}
```

### 回調訂單

- 請求方式: 依據各三方渠道自定義 (GET/POST)
- 請求地址: /api/v1/payment/notify/`{渠道代碼}`
- 請求格式: 依據各三方渠道自定義
- 請求參數: 依據各三方渠道自定義
- 返回結果: 驗證完成後將統整為以下格式回調 callback_url 給 IG 進行後續處理.

```json
{
  "amount": "存款金額, 單位為分",
  "real_amount": "實際存款金額, 單位為分",
  "order_no": "商戶訂單號",
  "trade_no": "三方交易號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "status": "狀態碼, 請參考狀態碼規範",
  "remark": "",
  "created_at": "GP 返回時間 (UTC)"
}
```

### 查詢訂單

- 文件位置: app/Payment/Driver/`{渠道代碼}`/OrderQuery.php
- 請求方式:
    - POST
    - content-type: application/json
- 請求地址: /api/v1/payment/query
- 請求參數:

```json
{
  "site_id": "商戶代號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "merchant_id": "商戶號",
  "merchant_key": "商戶密鑰",
  "endpoint_url": "三方網關查詢訂單地址",
  "order_id": "商戶訂單號"
}
```

- 返回結果: 與三方回調時 callback 的結果格式相同.

```json
{
  "amount": "存款金額, 單位為分",
  "real_amount": "實際存款金額, 單位為分",
  "order_no": "商戶訂單號",
  "trade_no": "三方交易號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "status": "狀態碼, 請參考狀態碼規範 (字串)",
  "remark": "",
  "created_at": "GP 返回時間 (UTC)"
}
```

## 代付接口

### 創建訂單

- 文件位置: app/Payment/Driver/{渠道代碼}/WithdrawCreate.php
- 請求方式:
    - POST
    - content-type: application/json
- 請求地址: /api/v1/withdraw/create
- 請求參數:

```json
{
  "site_id": "商戶代號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "endpoint_url": "三方網關創建訂單地址",
  "query_url": "三方網關查詢訂單地址",
  "callback_url": "三方回調時轉發通知 IG 的回調地址",
  "merchant_id": "商戶號",
  "merchant_key": "商戶密鑰",
  "order_id": "商戶訂單號",
  "amount": "存款金額, 單位為分",
  "currency": "幣別, 預設 CNY",
  "bank_name": "銀行名稱",
  "bank_code": "銀行代碼",
  "bank_branch_name": "銀行分行名稱",
  "bank_branch_code": "銀行分行代號",
  "bank_account": "銀行帳號 / 電子錢包 / 支付寶賬號",
  "user_name": "用戶名 / 取款人",
  "user_phone": "用戶手機號",
  "user_id": "用戶ID",
  "header_params": "額外的請求頭參數",
  "body_params": "額外的請求參數"
}
```

- 返回結果:

```json
{
  "order_no": "商戶订单号",
  "trade_no": "三方交易号"
}
```

### 回調訂單

- 請求方式: 依據各三方渠道自定義 (GET/POST)
- 請求地址: /api/v1/withdraw/notify/{渠道代碼}
- 請求格式: 依據各三方渠道自定義
- 請求參數: 依據各三方渠道自定義
- 返回結果: 驗證完成後將統整為以下格式回調 callback_url 給 IG 進行後續處理.

```json
{
  "amount": "存款金額, 單位為分",
  "fee": "手續費, 單位為分",
  "real_amount": "實際存款金額, 單位為分",
  "order_no": "商戶訂單號",
  "trade_no": "三方交易號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "status": "狀態碼, 請參考狀態碼規範 (字串)",
  "remark": "",
  "created_at": "GP 返回時間 (UTC)"
}
```

### 查詢訂單

- 文件位置: app/Payment/Driver/{渠道代碼}/WithdrawQuery.php
- 請求方式:
    - POST
    - content-type: application/json
- 請求地址: /api/v1/withdraw/query
- 請求參數:

```json
{
  "site_id": "商戶代號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "merchant_id": "商戶號",
  "merchant_key": "商戶密鑰",
  "endpoint_url": "三方網關查詢訂單地址",
  "order_id": "商戶訂單號"
}
```

- 返回結果: 與三方回調時 callback 的結果格式相同.

```json
{
  "amount": "存款金額, 單位為分",
  "fee": "手續費, 單位為分",
  "real_amount": "實際存款金額, 單位為分",
  "order_no": "商戶訂單號",
  "trade_no": "三方交易號",
  "payment_platform": "支付渠道代號",
  "payment_channel": "支付通道代號",
  "status": "狀態碼, 請參考狀態碼規範 (字串)",
  "remark": "",
  "created_at": "GP 返回時間 (UTC)"
}
```

## 創建新渠道

您可以使用以下命令在 terminal 創建新的代收渠道代碼脚手架.

```bash
php bin/hypertf.php gen:payment <name>
```

例如您想創建一個名為 Alipay 的代收脚手架, 可以執行以下命令:

```bash
php bin/hypertf.php gen:payment Alipay
```

假設您需要創建包含代付功能脚手架, 可以在命令中加入 ```-w```:

```bash
php bin/hypertf.php gen:payment Alipay -w
```

如果您想強制覆寫已經存在的腳手叫, 可以在命令中加入 ```-f```:

```bash
php bin/hypertf.php gen:payment Alipay -w -f
```

腳手架的代碼會放在 ```app/Payment/Driver``` 目錄下

請依據三方文檔調整標記 ```todo``` 的代碼, 並在 ```config/autoload/payment.php``` 中配置渠道.

當你完成一個段落的修改, 可以將 ```todo``` 註釋刪除, 透過查詢 ```todo``` 你可以清楚的知道哪些需求已完成.

* PhpStorm 会不断扫描您的项目以查找与特定TODO模式匹配的源代码中的注释，并将它们显示在TODO工具窗口中。

## 配置項

config/autoload/payment.php

請在 driver 下配置一個新的渠道, 示意如下

```php
return [
    'payment_notify_url'  => env('APP_HOST', '') . '/api/v1/payment/notify/', // 代收回調
    'withdraw_notify_url' => env('APP_HOST', '') . '/api/v1/withdraw/notify/', // 代付回調
    'return_url'          => 'https://www.baidu.com',
    'order_driver'        => OrderDrivers\CacheOrder::class,
    'driver'              => [
        'wt'     => [
            'class'  => Drivers\Wt\Driver::class,
            'config' => [
                'timezone'     => 'Asia/Shanghai',
                'create_order' => [
                    'method'      => 'post',
                    'body_format' => 'json',
                ],
                'query_order'  => [
                    'method'      => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        '渠道代號' => [
            'class' => Driver\{渠道代號}\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                    'token' => '',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                    'token' => '',
                ],
                'new' => '自定義',
            ],
        ],
        // ... 略
    ],
];
```

#### 全局配置

- payment_notify_url: 代收回調網址, 請保持預設值, 除非調整了路由
- withdraw_notify_url: 代付回調網址, 請保持預設值, 除非調整了路由
- return_url: 代收支付成功後轉跳網址
- order_driver: 訂單儲存方式驅動, 目前僅支持 CacheOrder, 如有其他需求可自行設計

#### 渠道配置

- timezone: 由於 ```IG``` 使用 UTC 時間請求, 需要配置三方渠道使用的時區
- create_order: 創建訂單請求規範
    - method: 請求方式, 目前支持 ```get``` 和 ```post```
    - body_format: 請求體格式, 目前支持 ```json``` 和 ```form```
- query_order: 查詢訂單請求規範
    - method: 請求方式, 目前支持 ```get``` 和 ```post```
    - body_format: 請求體格式, 目前支持 ```json``` 和 ```form```

若是配置不敷使用可隨時增加配置項例如 ```new```, 並在代碼中使用 ```$this->config['new']``` 調用.

## 附錄：訂單 Schema

| 欄位                     | 說明            | 型別        | 說明                   |
|------------------------|---------------|-----------|----------------------|
| id                     | 流水號           | int       |                      |
| type                   | 訂單類型          | string    | order / withdraw     |
| order_no               | 訂單號           | string    |                      |
| trade_no               | 三方交易號         | string    |                      |
| status                 | 訂單狀態          | int       | 參照訂單狀態               |
| site_id                | 站點代號          | string    |                      |
| payment_platform       | 三方渠道代號        | string    |                      |
| payment_channel        | 三方支付通道        | string    |                      |
| endpoint_url           | 三方建單網址        | string    |                      |
| query_url              | 三方查詢訂單網址      | string    |                      |
| callback_url           | 四方回調網址        | string    |                      |
| merchant_id            | 商戶號           | string    |                      | |
| merchant_key           | 商戶密鑰          | string    |                      |
| order_id               | 訂單號           | string    |                      |
| amount                 | 金額            | string    | 單位：分                 |
| real_amount            | 真實金額          | string    | 單位：分                 |
| fee                    | 手續費           | string    | 單位：分                 |
| commission             | 佣金            | string    | 單位：分                 |
| currency               | 幣別            | string    | 預設 CNY               |
| bank_name              | 銀行名稱          | string    | 代付必填                 |
| bank_code              | 銀行代碼          | string    | 新增                   |
| bank_account           | 銀行帳戶          | string    | 異動, 原 account_number |
| user_name              | 用戶名稱          | string    | 異動, 原 account_name   |
| user_phone             | 用戶手機號         | string    | 新增, 超商支付需要           |
| user_id                | 用戶ID          | string    | 新增, uid              |
| header_params          | header 額外參數   | string    |                      |
| body_params            | body 額外參數     | string    |                      |
| payee_name             | 代收：三方收款人      | string    | 收銀台                  |
| payee_bank_name        | 代收：三方收款銀行名稱   | string    | 收銀台                  |
| payee_bank_branch_name | 代收：三方收款銀行分行名稱 | string    | 收銀台                  |
| payee_bank_account     | 代收：三方收款銀行帳號   | string    | 收銀台                  |
| lock                   | 訂單鎖           | int       |                      |
| created_at             | 創建時間          | timestamp |                      |
| updated_at             | 更新時間          | timestamp | 三方回調時間               |
