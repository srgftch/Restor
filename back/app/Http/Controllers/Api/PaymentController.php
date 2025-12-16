<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // POST /api/payments - начальный этап оплаты
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'card_number' => 'required|string',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:' . date('Y'),
            'card_cvc' => 'required|string',
            'save_card' => 'boolean',
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $data = $v->validated();

        // Luhn check
        if (! $this->luhnCheck($data['card_number'])) {
            return response()->json(['message' => 'Invalid card number'], 422);
        }

        // expiry check
        $exp = \DateTime::createFromFormat('!m Y', sprintf('%02d %04d', $data['card_exp_month'], $data['card_exp_year']));
        if (! $exp) {
            return response()->json(['message' => 'Invalid expiry date'], 422);
        }
        $exp->modify('last day of this month 23:59:59');
        if ($exp < new \DateTime()) {
            return response()->json(['message' => 'Card expired'], 422);
        }

        // Бренд карты и последние 4 цифры
        $cardBrand = $this->detectCardBrand($data['card_number']);
        $last4 = substr(preg_replace('/\D/', '', $data['card_number']), -4);

        // Создаем запись payment
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'reservation_id' => $data['reservation_id'],
            'amount_rubles' => (int) round($data['amount'] * 100),
            'currency' => strtoupper($data['currency'] ?? 'RUB'),
            'status' => 'pending_verification', // используем строку напрямую
            'card_brand' => $cardBrand,
            'card_last4' => $last4,
            'meta' => [
                'save_card' => $data['save_card'] ?? false,
            ],
        ]);

        // Генерируем и сохраняем код подтверждения
        $smsCode = $this->generateSmsCode();
        $verificationToken = Str::random(32);

        Cache::put("payment_verification:{$verificationToken}", [
            'payment_id' => $payment->id,
            'sms_code' => $smsCode,
            'attempts' => 0,
        ], now()->addMinutes(10));

        // Возвращаем токен для перехода на страницу подтверждения
        return response()->json([
            'verification_token' => $verificationToken,
            'payment_id' => $payment->id,
            'sms_code' => $smsCode, // для тестов
            'message' => 'SMS code sent for verification',
        ]);
    }

    // POST /api/payments/verify-sms - подтверждение SMS кода
    public function verifySms(Request $request)
    {
        $v = Validator::make($request->all(), [
            'verification_token' => 'required|string',
            'sms_code' => 'required|string|size:3', // меняем на 3 цифры
        ]);

        if ($v->fails()) {
            return response()->json(['errors' => $v->errors()], 422);
        }

        $data = $v->validated();
        $cacheKey = "payment_verification:{$data['verification_token']}";
        $verificationData = Cache::get($cacheKey);

        if (!$verificationData) {
            return response()->json(['message' => 'Invalid or expired verification token'], 422);
        }

        // Проверяем количество попыток
        if ($verificationData['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Too many attempts. Please start over.'], 422);
        }

        // Проверяем код
        if ($verificationData['sms_code'] !== $data['sms_code']) {
            $verificationData['attempts']++;
            Cache::put($cacheKey, $verificationData, now()->addMinutes(10));

            $remainingAttempts = 3 - $verificationData['attempts'];
            return response()->json([
                'message' => 'Invalid SMS code',
                'remaining_attempts' => $remainingAttempts
            ], 422);
        }

        // Код верный - выполняем оплату
        $payment = Payment::findOrFail($verificationData['payment_id']);

        // Отмечаем время подтверждения
        $payment->update(['verified_at' => now()]);

        // Симуляция отправки в банк
        $result = $this->simulateBankRequest([
            'amount_rubles' => $payment->amount_rubles,
            'currency' => $payment->currency,
            'last4' => $payment->card_last4,
            'brand' => $payment->card_brand,
        ]);

        // Обновляем платеж
        $payment->update([
            'status' => $result['status'],
            'provider_reference' => $result['reference'] ?? null,
            'processed_at' => now(),
            'meta' => array_merge($payment->meta ?? [], [
                'bank_response' => $result,
            ]),
        ]);

        // Очищаем кэш
        Cache::forget($cacheKey);

        // Генерируем токен для редиректа на финальную страницу
        $resultToken = Str::random(32);
        Cache::put("payment_result:{$resultToken}", [
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ], now()->addMinutes(10));

        // Возвращаем статус платежа из обновленной записи
        return response()->json([
            'success' => true,
            'result_token' => $resultToken,
            'status' => $payment->status,
            'payment_id' => $payment->id,
        ]);
    }

    // GET /api/payments/result/{token} - получение результата оплаты
    public function getResult($token)
    {
        $cacheKey = "payment_result:{$token}";
        $resultData = Cache::get($cacheKey);

        if (!$resultData) {
            return response()->json(['message' => 'Invalid or expired result token'], 404);
        }

        $payment = Payment::find($resultData['payment_id']);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        return response()->json([
            'payment_id' => $payment->id,
            'status' => $payment->status,
            'amount_rubles' => $payment->amount_rubles,
            'currency' => $payment->currency,
            'card_brand' => $payment->card_brand,
            'card_last4' => $payment->card_last4,
            'provider_reference' => $payment->provider_reference,
            'created_at' => $payment->created_at,
        ]);
    }

    protected function luhnCheck(string $number): bool
    {
        $number = preg_replace('/\D/', '', $number);
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
            $alt = !$alt;
        }
        return ($sum % 10) === 0;
    }

    protected function detectCardBrand(string $number): string
    {
        $n = preg_replace('/\D/', '', $number);
        if (preg_match('/^220[0-4][0-9]{12}$/', $n)) return 'mir';
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $n)) return 'visa';
        if (preg_match('/^5[1-5][0-9]{14}$/', $n)) return 'mastercard';
        if (preg_match('/^3[47][0-9]{13}$/', $n)) return 'amex';
        if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $n)) return 'discover';
        return 'unknown';
    }

    protected function generateSmsCode(): string
    {
        return sprintf('%03d', rand(100, 999));
    }

    protected function simulateBankRequest(array $payload): array
    {
        $last = (int) substr($payload['last4'], -1);
        if ($last === 0) {
            return [
                'status' => 'declined',
                'reference' => Str::upper(Str::random(12)),
                'reason' => 'Insufficient funds (simulated)',
            ];
        }

        return [
            'status' => 'approved',
            'reference' => Str::upper(Str::random(12)),
            'approved_at' => now()->toDateTimeString(),
        ];
    }
}
