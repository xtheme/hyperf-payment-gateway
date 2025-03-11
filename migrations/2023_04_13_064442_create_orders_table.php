<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 10)->comment('訂單類型');
            $table->string('order_no')->comment('商戶訂單號');
            $table->string('trade_no')->comment('三方交易號');
            // 代收集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
            // 代付集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
            $table->unsignedTinyInteger('status')->comment('集成訂單狀態');
            $table->string('site_id', 20)->comment('站台代號');
            $table->string('payment_platform', 20)->comment('三方渠道');
            $table->string('payment_channel', 20)->comment('三方支付通道');
            $table->string('merchant_id', 50)->comment('三方商戶號');
            $table->string('merchant_key')->comment('三方商戶密鑰');
            $table->string('create_url')->comment('三方創建訂單網址');
            $table->string('query_url')->comment('三方查詢訂單網址');
            $table->string('callback_url')->comment('回調集成網關網址');
            $table->text('header_params')->comment('額外的請求頭參數');
            $table->text('body_params')->comment('額外的請求體參數');
            $table->string('amount', 20)->comment('代收代付金額(分)');
            $table->string('real_amount', 20)->comment('實際金額');
            $table->string('fee', 20)->comment('三方手續費');
            $table->string('commission', 20)->comment('佣金');
            $table->string('currency', 3)->default('CNY')->comment('幣別');
            $table->string('bank_name', 30)->default('')->comment('銀行名稱');
            $table->string('bank_code', 10)->default('')->comment('銀行代碼');
            $table->string('bank_branch_name', 30)->default('')->comment('銀行分行名稱');
            $table->string('bank_branch_code', 10)->default('')->comment('銀行分行代號');
            $table->string('bank_account', 50)->default('')->comment('銀行帳號');
            $table->string('user_name', 30)->default('')->comment('用戶名');
            $table->string('user_phone', 30)->default('')->comment('用戶手機號');
            $table->string('user_id', 50)->default('')->comment('用戶ID');
            $table->string('payee_name', 30)->default('')->comment('代收：三方收款人');
            $table->string('payee_bank_name', 30)->default('')->comment('代收：三方收款銀行名稱');
            $table->string('payee_bank_branch_name', 30)->default('')->comment('代收：三方收款銀行分行名稱');
            $table->string('payee_bank_account', 50)->default('')->comment('代收：三方收款銀行帳號');
            $table->string('payee_nonce', 20)->default('')->comment('代收：附言');
            $table->string('remark', 100)->default('')->comment('備註');
            $table->unsignedTinyInteger('lock')->default(0)->comment('鎖定狀態');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}
