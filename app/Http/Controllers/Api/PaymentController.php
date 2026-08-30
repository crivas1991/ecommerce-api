<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\MarkOrderAsPaidRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use OpenApi\Attributes as OA;
use UnexpectedValueException;

class PaymentController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    #[OA\Post(
        path: '/api/orders/{order}/pay',
        summary: 'Generar una sesion de pago de Stripe Checkout para una orden',
        description: 'Crea una Stripe Checkout Session con el total de la orden y devuelve la URL para completar el pago. Solo el dueno de la orden puede pagarla.',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, description: 'ID de la orden a pagar', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesion de checkout creada',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'checkout_url', type: 'string', example: 'https://checkout.stripe.com/c/pay/cs_test_xxx'),
                        new OA\Property(property: 'session_id', type: 'string', example: 'cs_test_xxx'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 403, description: 'La orden no pertenece al usuario', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, description: 'La orden ya fue pagada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function pay(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You are not authorized to pay for this order.',
            ], 403);
        }

        if ($order->status === 'paid') {
            return response()->json([
                'message' => 'This order has already been paid.',
            ], 422);
        }

        $order->load('items.product');

        $lineItems = $order->items->map(function ($item) use ($order) {
            return [
                'price_data' => [
                    'currency' => $order->currency,
                    'product_data' => [
                        'name' => $item->product->name,
                    ],
                    'unit_amount' => (int) round($item->unit_price * 100),
                ],
                'quantity' => $item->quantity,
            ];
        })->all();

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'metadata' => [
                'order_id' => (string) $order->id,
            ],
            'success_url' => config('app.url') . '/api/orders/' . $order->id . '?stripe_status=success',
            'cancel_url' => config('app.url') . '/api/orders/' . $order->id . '?stripe_status=cancel',
        ]);

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'stripe_checkout_session_id' => $session->id,
                'status' => 'pending',
                'amount' => $order->total,
                'currency' => $order->currency,
            ]
        );

        return response()->json([
            'checkout_url' => $session->url,
            'session_id' => $session->id,
        ]);
    }

    #[OA\Put(
        path: '/api/orders/{order}/mark-as-paid',
        summary: 'Marcar una orden como pagada manualmente (contingencia, sin pasar por Stripe)',
        description: 'Uso administrativo: registra el pago de una orden por un medio externo (efectivo, transferencia, etc.) cuando la pasarela de Stripe no aplica. El dueno de la orden no puede marcar su propia orden como pagada.',
        security: [['sanctum' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, description: 'ID de la orden', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'payment_method', type: 'string', nullable: true, example: 'cash'),
                    new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Pago recibido en efectivo, confirmado por el encargado de tienda.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Orden marcada como pagada', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 401, description: 'No autenticado', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 403, description: 'El dueno de la orden no puede marcarla como pagada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 404, description: 'Orden no encontrada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 422, description: 'La orden ya fue pagada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function markAsPaid(MarkOrderAsPaidRequest $request, Order $order): OrderResource|JsonResponse
    {
        if ($order->status === 'paid') {
            return response()->json([
                'message' => 'This order has already been paid.',
            ], 422);
        }

        if ($order->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot mark your own order as paid manually. This action must be performed by another authenticated account.',
            ], 403);
        }

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'status' => 'succeeded',
                'amount' => $order->total,
                'currency' => $order->currency,
                'metadata' => [
                    'manual' => true,
                    'marked_by' => $request->user()->id,
                    'payment_method' => $request->validated('payment_method'),
                    'note' => $request->validated('note'),
                ],
            ]
        );

        $order->update(['status' => 'paid']);
        $order->load('items.product', 'payment');

        return new OrderResource($order);
    }

    #[OA\Post(
        path: '/api/stripe/webhook',
        summary: 'Webhook de eventos de Stripe (uso interno de Stripe)',
        description: 'Endpoint publico invocado por Stripe para notificar eventos de pago. La firma se verifica con STRIPE_WEBHOOK_SECRET.',
        tags: ['Payments'],
        responses: [
            new OA\Response(response: 200, description: 'Evento procesado', content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Webhook handled.')])),
            new OA\Response(response: 400, description: 'Firma invalida', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ]
    )]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $this->markPaymentSucceeded($event->data->object);
                break;

            case 'checkout.session.async_payment_failed':
            case 'checkout.session.expired':
                $this->markPaymentFailed($event->data->object);
                break;
        }

        return response()->json(['message' => 'Webhook handled.']);
    }

    private function markPaymentSucceeded(StripeCheckoutSession $session): void
    {
        $payment = Payment::where('stripe_checkout_session_id', $session->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => 'succeeded',
            'stripe_payment_intent_id' => $session->payment_intent,
        ]);

        $payment->order()->update(['status' => 'paid']);
    }

    private function markPaymentFailed(StripeCheckoutSession $session): void
    {
        $payment = Payment::where('stripe_checkout_session_id', $session->id)->first();

        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'failed']);
        $payment->order()->update(['status' => 'failed']);
    }
}
