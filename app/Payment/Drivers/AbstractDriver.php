<?php

declare(strict_types=1);

namespace App\Payment\Drivers;

use App\Common\BotNotify;
use App\Common\HttpRequestPresenter;
use App\Constants\ReturnFormat;
use App\Exception\ApiException;
use App\Payment\Contracts\OrderDriverInterface;
use App\Payment\Traits\TimeTrait;
use Carbon\Carbon;
use FriendsOfHyperf\Http\Client\Http;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;
use function Hyperf\Support\make;

abstract class AbstractDriver
{
    use TimeTrait;

    protected OrderDriverInterface $orderDriver;

    #[Inject]
    protected StdoutLoggerInterface $logger;

    protected array $config; // config/autoload/payment.php 的配置

    protected array $requestHeaders = [];

    protected string $token = '';

    protected string $tokenType = '';

    protected bool $amountToDollar = false;  // 三方定義的金额单位是否为元, 預設集成請求的金額單位為分

    protected string $signField = 'sign'; // 三方签名字段

    protected string $notifySuccessText = 'success';

    protected string $notifyFailText = 'fail';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->orderDriver = make(config('payment.order_driver'));
    }

    public function createOrder(string $orderNo, array $data = [], string $type = 'order'): void
    {
        $this->orderDriver->createOrder($orderNo, $data, $type);
    }

    public function getFormData(string $orderNo, string $type = 'form'): array
    {
        return $this->orderDriver->getFormData($orderNo, $type);
    }

    public function createFormData(string $orderNo, array $data = [], string $type = 'form'): void
    {
        $this->orderDriver->createFormData($orderNo, $data, $type);
    }

    public function isOrderExists(string $orderNo, string $type = 'order'): bool
    {
        return $this->orderDriver->isOrderExists($orderNo, $type);
    }

    public function getOrder(string $orderNo, string $type = 'order'): array
    {
        return $this->orderDriver->getOrder($orderNo, $type);
    }

    public function updateOrder(string $orderNo, array $data = [], string $type = 'order'): void
    {
        $this->orderDriver->updateOrder($orderNo, $data, $type);
    }

    public function appendHeaders(string|array $header_params): void
    {
        if (is_string($header_params)) {
            $headers = [];
            $order['header_params'] = json_decode($header_params, true);

            foreach ($order['header_params'] as $key => $value) {
                $headers[$key] = $value;
            }
        } else {
            $headers = $header_params;
        }

        $this->withHeaders($headers);
    }

    public function withHeaders(array $headers = []): void
    {
        if (!empty($headers)) {
            $this->requestHeaders = array_merge($this->requestHeaders, $headers);
        }
    }

    public function withToken(string $token = '', string $type = 'Bearer'): void
    {
        $this->token = $token;
        $this->tokenType = $type;
    }

    /**
     * 依据三方规范方式请求
     *
     * @throws \Exception
     */
    public function sendRequest(string $endpointUrl, array $data = [], array $settings = []): array|ResponseInterface
    {
        // 請求三方時間點
        Context::set('paymentForwardChannelCreatedAt', Carbon::now()->getTimestampMs());

        $client = Http::connectTimeout(5);

        /**
         * 添加標頭
         */
        $client->withHeaders($this->requestHeaders);

        /**
         * 添加 Bearer 令牌
         */
        if ('' != $this->token) {
            $client->withToken($this->token, $this->tokenType);
        }

        /**
         * 請求三方接口方式
         */
        $method = $settings['method'] ?? strtolower($settings['method']) ?? 'post';

        if (!in_array($method, ['get', 'post'])) {
            throw new \Exception('PG Error: 不被支援的請求方式 [' . $method . ']');
        }

        if ('post' == $method) {
            $this->logger->info(sprintf('send [POST] request to %s', $endpointUrl), $data);

            /**
             * 請求格式
             * form => application/x-www-form-urlencoded
             * json => application/json
             */
            $body_format = $settings['body_format'] ?? strtolower($settings['body_format']) ?? 'json';

            if ('form' == strtolower($body_format)) {
                $client->asForm();
            }
        }

        if ('get' == $method) {
            $this->logger->info(sprintf('send [GET] request to %s', $endpointUrl . '?' . http_build_query($data)));
        }

        try {
            $response = $client->{$method}($endpointUrl, $data);
        } catch (\Exception $e) {
            Context::set('tp_class_name', get_class($this)); // 完整class
            Context::set('tp_url', $endpointUrl);
            Context::set('tp_request_headers', $client->getOptions()['headers'] ?? []);
            Context::set('tp_request_body', $data);
            Context::set('tp_exception_msg', $e->getMessage());
            $this->notifyTgBot();

            throw new \Exception('TP Error: ' . str_replace("\n", ' ', $e->getMessage()));
        }

        $return_format = $settings['return_format'] ?? ReturnFormat::JSON;

        $responseData = [];

        if (ReturnFormat::JSON == $return_format) {
            $responseData = $response->json();
        } elseif (ReturnFormat::PARAM == $return_format) {
            parse_str($response->body(), $responseData);
        } elseif ('html' == $return_format) {
            $responseData['html'] = $response->body();
        } else {
            $responseData = $response->body();
        }

        // http status code != 200
        $response->throw(function ($response, $e) use ($endpointUrl, $data, $responseData, $client) {
            $this->logger->error(sprintf('TP response error: %s', str_replace("\n", ' ', $e->getMessage())));

            Context::set('tp_class_name', get_class($this)); // 完整class
            Context::set('tp_url', $endpointUrl);
            Context::set('tp_request_headers', $client->getOptions()['headers'] ?? []);
            Context::set('tp_request_body', $data);
            Context::set('tp_status', $response->status());
            Context::set('tp_exception_msg', $e->getMessage());

            if (is_array($responseData)) {
                Context::set('tp_response', json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            } else {
                Context::set('tp_response', $responseData);
            }
            $this->notifyTgBot();

            throw new \Exception('TP Error: ' . str_replace("\n", ' ', $e->getMessage()));
        });

        // 請求三方返回的結果如果無法轉為陣列需要排查
        if (!is_array($responseData)) {
            BotNotify::send($response->body());

            throw new \Exception('三方返回的結果無法轉為陣列: ' . $responseData);
        }

        // 三方返回時間點
        Context::set('channelResponsePaymentCreatedAt', Carbon::now()->getTimestampMs());

        $this->logger->info(sprintf('got response from %s', $endpointUrl), $responseData);

        return $responseData;
    }

    /**
     * 回调集成网关
     */
    public function sendCallback(string $callbackUrl, string $orderNo, array $params = []): array
    {
        $settings = [
            'method' => 'post',
            'body_format' => 'json',
            'token' => '',
        ];

        $log = sprintf('Order #%s 回調集成 Start', $orderNo);
        $this->logger->info($log, $params);

        $log = sprintf('Order #%s 回調集成 Url => %s', $orderNo, $callbackUrl);
        $this->logger->info($log);

        try {
            $response = $this->sendRequest($callbackUrl, $params, $settings);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        $log = sprintf('Order #%s 回調集成 End', $orderNo);
        $this->logger->info($log, $response);

        return $response;
    }

    public function getNotifyUrl(string $platform): string
    {
        return config('payment.payment_notify_url') . $platform;
    }

    public function getWithdrawNotifyUrl(string $platform): string
    {
        return config('payment.withdraw_notify_url') . $platform;
    }

    public function getReturnUrl(): string
    {
        return config('payment.return_url');
    }

    public function getRedirectUrl(string $order_no): string
    {
        return config('app_host') . '/redirect/' . $order_no;
    }

    public function getCashierUrl(string $order_no): string
    {
        return '/cashier/' . $order_no;
    }

    protected function notifyTgBot(): void
    {
        $context = HttpRequestPresenter::make()->present();
        BotNotify::send($context);
    }

    /**
     * 金额单位转换, 使用 "元" 或 "分"
     */
    protected function convertAmount(int|string $amount): string
    {
        $amount = (string) $amount;

        if (true === $this->amountToDollar) {
            $amount = bcdiv($amount, '100', 2);
            // $amount = sprintf('%.2f', $amount / 100);
        }

        return $amount;
    }

    /**
     * 反转金额单位
     */
    protected function revertAmount(int|string|float $amount): string
    {
        $amount = (string) $amount;

        if (true === $this->amountToDollar) {
            $amount = bcmul($amount, '100');
            // $amount = sprintf('%.2f', $amount * 100);
        }

        return $amount;
    }

    /**
     * 簽名規則
     */
    abstract protected function getSignature(array $data, string $signatureKey): string;

    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }

    /**
     * 校驗回調簽名
     */
    protected function verifySignature($data, $signatureKey, string $type = 'order'): bool
    {
        $sign = $data[$this->signField] ?? '';
        // $this->logger->info('signature => ' . $sign);

        if ('order' == $type) {
            $check_sign = $this->getSignature($data, $signatureKey);
        } else {
            $check_sign = $this->getWithdrawSignature($data, $signatureKey);
        }
        // $this->logger->info('check signature => ' . $check_sign);

        return $sign === $check_sign;
    }

    /**
     * 生成集成網關簽名, 假如集成需要更高的安全機制可以用來驗籤
     */
    protected function genAuthKey(array $data, string $signatureKey): string
    {
        // 1. 字典排序
        ksort($data);

        // 2. 排除空字串欄位參與簽名
        $tempData = array_filter($data, fn ($value) => '' !== $value, ARRAY_FILTER_USE_BOTH);

        // 3. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($tempData));

        // 4. $tempStr 拼接密鑰
        $tempStr .= '&sign=' . $signatureKey;

        // 5. sign = $tempStr 進行 MD5 後轉為小寫
        return strtolower(md5($tempStr));
    }
}
