<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // платёжные операции только для залогиненных
    }

    // POST /api/payments
    public function store(Request $request)
    {
        // валидируем вход
        $v = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'card_number' => 'required|string',
            'card_exp_month' => 'required|integer|min:1|max:12',
            'card_exp_year' => 'required|integer|min:2000',
            'card_cvc' => 'required|string',
            'save_card' => 'boolean', // опционально
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

        // Бренд карты и last4
        $cardBrand = $this->detectCardBrand($data['card_number']);
        $last4 = substr(preg_replace('/\D/', '', $data['card_number']), -4);

        // НЕ логируем полный номер и CVV! Удаляем их из $data
        // (они уже пришли — но мы не сохраняем и не логируем)
        unset($data['card_number'], $data['card_cvc']);

        // Получаем reservation и сумму в центах
        $reservation = Reservation::findOrFail($v->validated()['reservation_id']);
        $amountCents = (int) round($data['amount'] * 100);
        $currency = $data['currency'] ?? 'USD';

        // Создадим запись payment в статусе pending
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'reservation_id' => $reservation->id,
            'amount_rubles' => $amountCents,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'card_brand' => $cardBrand,
            'card_last4' => $last4,
            'meta' => [
                'save_card' => $data['save_card'] ?? false,
            ],
        ]);

        // Симуляция отправки в банк
        $result = $this->simulateBankRequest([
            'amount_rubles' => $amountCents,
            'currency' => $currency,
            'last4' => $last4,
            'brand' => $cardBrand,
        ]);

        // Обновляем payment в зависимости от результата
        $payment->update([
            'status' => $result['status'], // approved | declined | error
            'provider_reference' => $result['reference'] ?? null,
            'meta' => array_merge($payment->meta ?? [], ['bank_response' => $result]),
        ]);

        // Возвращаем ответ фронту (не возвращаем номер/ CVV)
        return response()->json([
            'id' => $payment->id,
            'status' => $payment->status,
            'provider_reference' => $payment->provider_reference,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'card_brand' => $payment->card_brand,
            'card_last4' => $payment->card_last4,
            'created_at' => $payment->created_at,
        ], $payment->status === 'approved' ? 200 : 402);
    }

    // ---- вспомогательные методы ----

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
        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $n)) return 'visa';
        if (preg_match('/^5[1-5][0-9]{14}$/', $n)) return 'mastercard';
        if (preg_match('/^3[47][0-9]{13}$/', $n)) return 'amex';
        if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $n)) return 'discover';
        return 'unknown';
    }

    // very simple bank simulator: approve if last digit != 0, otherwise decline
    protected function simulateBankRequest(array $payload): array
    {
        // deterministic rule for tests: если last4 заканчивается на 0 => decline
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
