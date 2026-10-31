<?php

namespace PaymentModule\Tests\Feature;

use PaymentModule\Enums\PaymentStatus;
use PaymentModule\Models\Payment;
use PaymentModule\Tests\TestCase;

class PaymentModelTest extends TestCase
{
    public function test_factory_creates_a_pending_payment_by_default(): void
    {
        $payment = Payment::factory()->create();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->isPending());
        $this->assertNull($payment->transaction_id);
    }

    public function test_scopes_filter_by_status(): void
    {
        Payment::factory()->count(2)->pending()->create();
        Payment::factory()->count(3)->successful()->create();
        Payment::factory()->count(4)->failed()->create();

        $this->assertSame(2, Payment::pending()->count());
        $this->assertSame(3, Payment::successful()->count());
        $this->assertSame(4, Payment::failed()->count());
    }

    public function test_mark_as_paid_stores_reference_and_date(): void
    {
        $payment = Payment::factory()->pending()->create();

        $payment->markAsPaid('REF-1');

        $fresh = $payment->fresh();
        $this->assertTrue($fresh->isSuccessful());
        $this->assertSame('REF-1', $fresh->reference_id);
        $this->assertNotNull($fresh->paid_at);
    }

    public function test_mark_as_failed_stores_the_reason(): void
    {
        $payment = Payment::factory()->pending()->create();

        $payment->markAsFailed('Cancelled');

        $fresh = $payment->fresh();
        $this->assertTrue($fresh->isFailed());
        $this->assertSame('Cancelled', $fresh->failure_reason);
    }

    public function test_status_enum_reports_final_states(): void
    {
        $this->assertFalse(PaymentStatus::Pending->isFinal());
        $this->assertTrue(PaymentStatus::Success->isFinal());
        $this->assertTrue(PaymentStatus::Failed->isFinal());
    }
}
