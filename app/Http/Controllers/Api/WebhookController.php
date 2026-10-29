<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\AlipayService;
use App\Services\WechatPayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected WechatPayService $wechatPay,
        protected AlipayService $alipayService,
    ) {}

    /**
     * Handle WeChat Pay callback notification.
     * POST /api/webhook/wechat
     */
    public function wechat(Request $request)
    {
        $xml = $request->getContent();

        Log::info('WeChat Pay callback received', ['xml' => $xml]);

        $data = $this->wechatPay->verifyCallback($xml);

        if (!$data) {
            Log::warning('WeChat Pay callback verification failed');
            return response($this->wechatPay->failResponse('Signature verify failed'), 200)
                ->header('Content-Type', 'text/xml');
        }

        if (($data['result_code'] ?? '') !== 'SUCCESS') {
            Log::warning('WeChat Pay result_code not SUCCESS', $data);
            return response($this->wechatPay->successResponse(), 200)
                ->header('Content-Type', 'text/xml');
        }

        try {
            $this->paymentService->handleWechatCallback($data);
            Log::info('WeChat Pay callback processed successfully', [
                'out_trade_no' => $data['out_trade_no'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('WeChat Pay callback processing error: ' . $e->getMessage());
        }

        return response($this->wechatPay->successResponse(), 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Alipay callback notification.
     * POST /api/webhook/alipay
     */
    public function alipay(Request $request)
    {
        $data = $request->all();

        Log::info('Alipay callback received', $data);

        // 1. Verify signature
        $verified = $this->alipayService->verifyNotify($data);
        if (!$verified) {
            Log::warning('Alipay callback signature verification failed', [
                'out_trade_no' => $data['out_trade_no'] ?? null,
            ]);
            return response('fail');
        }

        // 2. Check trade_status
        $tradeStatus = $verified['trade_status'] ?? '';
        if (!in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            Log::info('Alipay callback: trade_status not success', [
                'trade_status' => $tradeStatus,
                'out_trade_no' => $verified['out_trade_no'] ?? null,
            ]);
            return response('success');
        }

        // 3. Process payment
        try {
            $this->paymentService->handleAlipayCallback($verified);
            Log::info('Alipay callback processed successfully', [
                'out_trade_no' => $verified['out_trade_no'] ?? null,
                'trade_no' => $verified['trade_no'] ?? null,
                'total_amount' => $verified['total_amount'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Alipay callback processing error: ' . $e->getMessage());
        }

        return response('success');
    }
}
