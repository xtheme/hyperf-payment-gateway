<?php

declare(strict_types=1);

namespace App\Model;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $order_no
 * @property string $trade_no
 * @property int $status
 * @property string $site_id
 * @property string $payment_platform
 * @property string $payment_channel
 * @property string $merchant_id
 * @property string $merchant_key
 * @property string $create_url
 * @property string $query_url
 * @property string $callback_url
 * @property string $header_params
 * @property string $body_params
 * @property string $amount
 * @property string $real_amount
 * @property string $fee
 * @property string $commission
 * @property string $currency
 * @property string $bank_name
 * @property string $bank_code
 * @property string $bank_branch_name
 * @property string $bank_branch_code
 * @property string $bank_account
 * @property string $user_name
 * @property string $user_phone
 * @property string $user_id
 * @property string $payee_name
 * @property string $payee_bank_name
 * @property string $payee_bank_branch_name
 * @property string $payee_bank_account
 * @property int $lock
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Order extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'orders';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = ['*'];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = ['id' => 'integer', 'status' => 'integer', 'lock' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
