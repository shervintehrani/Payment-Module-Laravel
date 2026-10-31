<?php

namespace PaymentModule\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Event;
use PaymentModule\Events\PaymentFailed;
use PaymentModule\Events\PaymentSucceeded;
use PaymentModule\Models\Payment;
use PaymentModule\Tests\TestCase;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Receipt;
use Shetabit\Multipay\RedirectionForm;

class PaymentFlowTest extends TestCase
{
    /* ------------------------------ initiate ------------------------------ */

    public function test_the_payment_form_is_displayed(): void
    {
        $this->get('/payment')->assertOk()->assertSee('Make a payment');
    }

    public function test_initiate_stores_a_pending_payment_and_redirects_to_the_gateway(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('purchase')->once()->andReturnUsing(
            function ($invoice, $callback) use ($gateway) {
                $callback(null, 'AUTH-123'); // the gateway issues a transaction id

                return $gateway;
            }
        );
        $gateway->shouldReceive('pay')->once()
            ->andReturn(new RedirectionForm('https://gateway.test/pay/AUTH-123', [], 'GET'));

        $this->post('/payment/initiate', ['amount' => 5000])
            ->assertRedirect('https://gateway.test/pay/AUTH-123');

        $this->assertDatabaseHas('payments', [
            'amount' => 5000,
            'gateway' => 'zarinpal',
            'status' => 'pending',
            'transaction_id' => 'AUTH-123',
        ]);
    }

    public function test_initiate_validates_the_minimum_amount(): void
    {
        $this->post('/payment/initiate', ['amount' => 10])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_initiate_rejects_unknown_gateways(): void
    {
        $this->post('/payment/initiate', ['amount' => 5000, 'gateway' => 'nope'])
            ->assertSessionHasErrors('gateway');
    }

    public function test_a_gateway_failure_marks_the_payment_as_failed_without_leaking_details(): void
    {
        Event::fake([PaymentFailed::class]);

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('purchase')->once()->andThrow(new \Exception('merchant secret exploded'));

        $this->post('/payment/initiate', ['amount' => 5000])
            ->assertSessionHasErrors('payment')
            ->assertSessionDoesntHaveErrors('amount');

        $this->assertDatabaseHas('payments', ['status' => 'failed']);
        $this->assertStringNotContainsString('merchant secret', session('errors')->first('payment'));
        Event::assertDispatched(PaymentFailed::class);
    }

    /* ------------------------------ callback ------------------------------ */

    public function test_a_successful_callback_settles_the_payment(): void
    {
        Event::fake([PaymentSucceeded::class]);
        $payment = Payment::factory()->create(['transaction_id' => 'AUTH-1', 'amount' => 7000]);

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('verify')->once()->andReturn(new Receipt('zarinpal', 'REF-999'));

        $this->get('/payment/callback?Authority=AUTH-1&Status=OK')
            ->assertOk()
            ->assertSee('Payment successful');

        $fresh = $payment->fresh();
        $this->assertTrue($fresh->isSuccessful());
        $this->assertSame('REF-999', $fresh->reference_id);
        Event::assertDispatched(PaymentSucceeded::class);
    }

    public function test_a_rejected_callback_marks_the_payment_as_failed(): void
    {
        Event::fake([PaymentFailed::class]);
        $payment = Payment::factory()->create(['transaction_id' => 'AUTH-2']);

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('verify')->once()->andThrow(new InvalidPaymentException('Cancelled', 1));

        $this->get('/payment/callback?Authority=AUTH-2&Status=NOK')
            ->assertOk()
            ->assertSee('Payment failed');

        $this->assertTrue($payment->fresh()->isFailed());
        Event::assertDispatched(PaymentFailed::class);
    }

    public function test_a_technical_error_keeps_the_payment_pending_so_it_can_be_retried(): void
    {
        $payment = Payment::factory()->create(['transaction_id' => 'AUTH-3']);

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('verify')->once()->andThrow(new \RuntimeException('timeout'));

        $this->get('/payment/callback?Authority=AUTH-3')
            ->assertOk()
            ->assertSee('Payment status unknown');

        $this->assertTrue($payment->fresh()->isPending());
    }

    public function test_the_callback_is_idempotent(): void
    {
        $payment = Payment::factory()->successful()->create(['transaction_id' => 'AUTH-4']);

        $gateway = $this->fakeGateway();
        $gateway->shouldNotReceive('verify'); // already settled: the bank is not asked again

        $this->get('/payment/callback?Authority=AUTH-4')->assertOk();

        $this->assertTrue($payment->fresh()->isSuccessful());
    }

    public function test_the_callback_also_accepts_post_requests(): void
    {
        Payment::factory()->create(['transaction_id' => 'AUTH-5']);

        $gateway = $this->fakeGateway();
        $gateway->shouldReceive('verify')->once()->andReturn(new Receipt('zarinpal', 'REF-5'));

        $this->post('/payment/callback', ['Authority' => 'AUTH-5'])->assertOk();
    }

    public function test_an_unknown_transaction_returns_404(): void
    {
        $this->get('/payment/callback?Authority=does-not-exist')->assertNotFound();
        $this->get('/payment/callback')->assertNotFound();
    }

    /* ------------------------------ history ------------------------------ */

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/payment/history')->assertUnauthorized();
    }

    public function test_history_only_lists_the_signed_in_users_payments(): void
    {
        $mine = Payment::factory()->forUser(1)->create();
        $theirs = Payment::factory()->forUser(2)->create();

        $user = new User();
        $user->forceFill(['id' => 1]);

        $this->actingAs($user)
            ->get('/payment/history')
            ->assertOk()
            ->assertSee($mine->order_id)
            ->assertDontSee($theirs->order_id);
    }
}
