<?php

declare(strict_types=1);

use App\Controller\Api;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');

Router::get('/favicon.ico', function () {
    return '';
});

/**
 * 轉跳三方收銀台
 * 台灣的金流調用時不會返回支付頁面網址，而是透過表單提交後轉跳到三方支付頁面
 * 所以自創一個收銀台頁面(自動送出表單)網址給前端，保持原本返回 pay_url 給前端的流程不變
 */
Router::get('/redirect/[{order_no}]', [Api\PaymentController::class, 'redirect']);

/**
 * 銀行資訊頁 (收銀台)
 * 某些支付方式如銀行轉帳, 三方僅返回銀行帳號相關資料 (payee_*)
 * PG 必須產生銀行資訊的畫面並提供網址給集成 (四方)
 */
Router::get('/cashier/[{order_no}]', [Api\PaymentController::class, 'cashier']);

// API v1
Router::addGroup('/api/v1', function () {
    // 代收
    Router::post('/payment/create', [Api\PaymentController::class, 'create']);
    Router::post('/payment/query', [Api\PaymentController::class, 'query']);
    Router::get('/payment/notify/{payment_platform}', [Api\PaymentController::class, 'notify']);
    Router::post('/payment/notify/{payment_platform}', [Api\PaymentController::class, 'notify']);
    // todo 測試接口, 在運營環境禁用?
    Router::post('/payment/mock/{action}/{order_no}', [Api\PaymentController::class, 'mock']);

    // 代付
    Router::post('/withdraw/create', [Api\WithdrawController::class, 'create']);
    Router::post('/withdraw/query', [Api\WithdrawController::class, 'query']);
    Router::get('/withdraw/notify/{payment_platform}', [Api\WithdrawController::class, 'notify']);
    Router::post('/withdraw/notify/{payment_platform}', [Api\WithdrawController::class, 'notify']);
    // todo 測試接口, 在運營環境禁用?
    Router::post('/withdraw/mock/{action}/{order_no}', [Api\WithdrawController::class, 'mock']);

    // 查詢商戶餘額
    Router::post('/merchant/balance', [Api\MerchantController::class, 'balance']);
}, [
    'middleware' => [
        // Middleware\DecryptRequestMiddleware::class,
        // Middleware\RateLimiterMiddleware::class,
        // Middleware\EncryptResponseMiddleware::class,
    ],
]);

// 服務監控
Router::get('/metrics', function () {
    $registry = Hyperf\Context\ApplicationContext::getContainer()->get(Prometheus\CollectorRegistry::class);
    $renderer = new Prometheus\RenderTextFormat();

    return $renderer->render($registry->getMetricFamilySamples());
});
