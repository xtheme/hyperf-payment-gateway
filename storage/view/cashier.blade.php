<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>收款信息 {{ $order_no }}</title>
</head>
<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
    }

    .content {
        margin: 30px auto;
        width: 500px;
        padding: 16px;
        background-color: #f9f9f9;
        border: 1px solid #ccc;
        border-radius: 6px;
        filter: drop-shadow(5px 5px 5px rgba(193, 192, 192, 0.8));
    }

    .countdown {
        font-size: 24px;
        text-align: center;
        color: #059ccc;
        padding: 8px 0;
    }

    .warning {
        text-align: center;
        color: #d53b30;
        padding: 8px 0;
    }

    dl {
        padding: 8px 0;
        display: grid;
        grid-gap: 4px 16px;
        grid-template-columns: max-content;
    }

    dt {
        color: #454545;
        min-width: 100px;
    }

    dd {
        font-weight: bold;
        margin: 0;
        grid-column-start: 2;
    }

    .copy {
        cursor: pointer;
        float: right;
        display: inline-block;
        margin-right: 26px;
        font-weight: normal;
        font-size: 14px;
        line-height: 1;
        color: #059ccc;
        background-color: #fefefe;
        border: 1px solid #b7b7b7;
        border-radius: 4px;
        filter: drop-shadow(5px 5px 5px rgba(193, 192, 192, 0.8));
        padding: 6px 8px;
    }
</style>
<body>

<div class="content">
    <div class="countdown">倒数计时 <span id="timer">--</span>
    </div>
    <div class="warning">快速上分限银行卡转银行卡，若使用支付宝或微信等非银行卡支付将不会快速上分。 ！！转帐金额必须与入款金额一致，请勿记忆打款！！</div>
    <dl>
        <dt>金额</dt>
        <dd>
            {{ $amount }}
        </dd>
    </dl>
    <dl>
        <dt>收款人</dt>
        <dd>
            {{ $payee_name }}
            <div class="copy" onclick="copyText('{{ $payee_name }}')">复制</div>
        </dd>
    </dl>
    <dl>
        <dt>收款帐户</dt>
        <dd>
            {{ $payee_bank_account }}
            <div class="copy" onclick="copyText('{{ $payee_bank_account }}')">复制</div>
        </dd>
    </dl>
    <dl>
        <dt>开户支行</dt>
        <dd>
            {{ $payee_bank_branch_name }}
            <div class="copy" onclick="copyText('{{ $payee_bank_branch_name }}')">复制</div>
        </dd>
    </dl>
    <dl>
        <dt>开户银行</dt>
        <dd>
            {{ $payee_bank_name }}
            <div class="copy" onclick="copyText('{{ $payee_bank_name }}')">复制</div>
        </dd>
    </dl>
    <div class="warning">复制实际存入金额进行入款，就可以快速到账唷</div>
</div>

<script>
    function copyText(text) {
        // Copy the text inside the text field
        navigator.clipboard.writeText(text);
    }

    document.getElementById('timer').innerHTML = '9:59';

    startTimer();

    function startTimer() {
        const presentTime = document.getElementById('timer').innerHTML;
        const timeArray = presentTime.split(/[:]+/);
        let m = timeArray[0];
        const s = checkSecond((timeArray[1] - 1));
        if (s == 59) {
            m = m - 1
        }
        if (m < 0) {
            return
        }

        document.getElementById('timer').innerHTML =
            m + ":" + s;
        console.log(m)
        setTimeout(startTimer, 1000);
    }

    function checkSecond(sec) {
        if (sec < 10 && sec >= 0) {
            sec = "0" + sec
        }

        // add zero in front of numbers < 10
        if (sec < 0) {
            sec = "59"
        }

        return sec;
    }
</script>
</body>
</html>