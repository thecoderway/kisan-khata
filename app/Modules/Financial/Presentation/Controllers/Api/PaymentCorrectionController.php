<?php

namespace App\Modules\Financial\Presentation\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Modules\Financial\Application\PaymentReversalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentCorrectionController extends Controller
{
    public function store(Request $request, int $customer, int $payment, PaymentReversalService $corrections): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:1', 'max:500'],
            'replacement' => ['sometimes', 'nullable', 'array'],
            'replacement.amount' => ['required_with:replacement', 'numeric', 'gt:0', 'decimal:0,2'],
            'replacement.payment_method' => ['nullable', 'string', 'max:32'],
            'replacement.reference_number' => ['nullable', 'string', 'max:128'],
            'replacement.description' => ['nullable', 'string', 'max:5000'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        abort_if(! is_string($idempotencyKey) || trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 255, 422, 'A valid Idempotency-Key header is required.');

        $customerModel = Customer::query()->findOrFail($customer);
        $paymentModel = Payment::query()
            ->where('customer_id', $customerModel->getKey())
            ->findOrFail($payment);

        $result = $corrections->correct(
            $request->user(),
            $customerModel,
            $paymentModel,
            $data['reason'],
            $idempotencyKey,
            $data['replacement'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'reversal' => [
                    'id' => $result['reversal']->getKey(),
                    'payment_number' => $result['reversal']->payment_number,
                    'amount' => $result['reversal']->amount,
                    'payment_date' => $result['reversal']->payment_date->format('Y-m-d'),
                ],
                'replacement' => $result['replacement'] === null ? null : [
                    'id' => $result['replacement']->getKey(),
                    'payment_number' => $result['replacement']->payment_number,
                    'amount' => $result['replacement']->amount,
                    'payment_date' => $result['replacement']->payment_date->format('Y-m-d'),
                ],
            ],
        ], 201);
    }
}
