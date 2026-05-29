<?php

namespace App\Services\Payments\Toyyibpay;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ToyyibpayClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $secretKey,
        protected string $categoryCode,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (string) config('homestay.channels.toyyibpay.base_url'),
            (string) config('homestay.channels.toyyibpay.secret_key'),
            (string) config('homestay.channels.toyyibpay.category_code'),
        );
    }

    public function createBill(Booking $booking, Payment $payment, string $returnUrl, string $callbackUrl): array
    {
        $payload = [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
            'billName' => "Booking {$booking->reference}",
            'billDescription' => "Stay at {$booking->property->name} ({$booking->check_in->toDateString()} → {$booking->check_out->toDateString()})",
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => (int) round($payment->amount * 100),
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => $callbackUrl,
            'billExternalReferenceNo' => $payment->public_id,
            'billTo' => $booking->bookingGuests()->where('is_lead', true)->value('full_name') ?? 'Guest',
            'billEmail' => $booking->bookingGuests()->where('is_lead', true)->value('email') ?? 'noreply@tempahlah.com',
            'billPhone' => $booking->bookingGuests()->where('is_lead', true)->value('phone') ?? '0000000000',
            'billPaymentChannel' => 2,
            'billContentEmail' => 'Thank you for booking with Tempahlah.',
            'billChargeToCustomer' => 1,
        ];

        $response = Http::asForm()
            ->post($this->baseUrl.'/index.php/api/createBill', $payload)
            ->throw()
            ->json();

        if (empty($response[0]['BillCode'])) {
            Log::error('Toyyibpay createBill failed', $response);
            throw new \RuntimeException('Toyyibpay bill creation failed.');
        }

        $payment->update([
            'gateway_provider' => 'toyyibpay',
            'gateway_ref' => $response[0]['BillCode'],
            'status' => Payment::STATUS_PROCESSING,
        ]);

        return [
            'bill_code' => $response[0]['BillCode'],
            'payment_url' => $this->baseUrl.'/'.$response[0]['BillCode'],
        ];
    }

    public function verifyCallback(array $payload, ?string $signatureHeader = null): bool
    {
        if (empty($payload['billcode']) || empty($payload['status_id'])) {
            return false;
        }

        $check = Http::asForm()
            ->post($this->baseUrl.'/index.php/api/getBillTransactions', [
                'userSecretKey' => $this->secretKey,
                'billCode' => $payload['billcode'],
            ])
            ->json();

        return is_array($check) && ! empty($check);
    }
}
