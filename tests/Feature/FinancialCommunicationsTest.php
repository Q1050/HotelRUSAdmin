<?php

namespace Tests\Feature;

use App\Jobs\DeliverFinancialCommunication;
use App\Models\CorporateAccount;
use App\Models\CorporateInvoice;
use App\Models\FinancialCommunication;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Finance\FinancialCommunicator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $account = CorporateAccount::create(['name' => 'Email Company', 'code' => 'EMAIL', 'status' => 'active', 'email' => 'billing@company.test', 'payment_terms_days' => 30]);
        $invoice = CorporateInvoice::create(['corporate_account_id' => $account->id, 'number' => 'INV-EMAIL-1', 'status' => 'issued', 'currency' => 'USD', 'issue_date' => today(), 'due_date' => today()->addDays(3), 'subtotal' => 100, 'total' => 100, 'balance' => 100]);

        return compact('manager', 'account', 'invoice');
    }

    public function test_manager_can_queue_and_monitor_a_financial_email(): void
    {
        Queue::fake();
        ['manager' => $manager, 'invoice' => $invoice] = $this->invoice();
        $this->actingAs($manager)->post(route('dashboard.communications.send'), ['document_type' => 'invoice', 'document_id' => $invoice->id, 'recipient' => 'billing@company.test'])->assertSessionHasNoErrors();
        $delivery = FinancialCommunication::firstOrFail();
        $this->assertSame('queued', $delivery->status);
        Queue::assertPushed(DeliverFinancialCommunication::class, fn ($job) => $job->deliveryId === $delivery->id);
        $this->actingAs($manager)->get(route('dashboard.communications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Communications/Index')->where('deliveries.0.recipient', 'billing@company.test'));
    }

    public function test_delivery_sends_a_pdf_and_tracks_opening(): void
    {
        Mail::fake();
        ['invoice' => $invoice] = $this->invoice();
        $hotel = Hotel::firstOrFail();
        app()->instance('currentHotel', $hotel);
        $delivery = app(FinancialCommunicator::class)->queue($hotel, 'invoice', $invoice->id, 'billing@company.test');
        (new DeliverFinancialCommunication($delivery->id))->handle(app(FinancialCommunicator::class));
        $this->assertSame('sent', $delivery->fresh()->status);
        $this->assertStringStartsWith('%PDF-1.4', app(FinancialCommunicator::class)->document($delivery)['pdf']);
        $url = URL::temporarySignedRoute('communications.open', now()->addMinute(), ['delivery' => $delivery->id]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/gif');
        $this->assertNotNull($delivery->fresh()->opened_at);
    }

    public function test_scheduler_queues_due_reminder_only_once(): void
    {
        Queue::fake();
        ['manager' => $manager] = $this->invoice();
        $hotel = Hotel::firstOrFail();
        app()->instance('currentHotel', $hotel);
        $settings = app(FinancialCommunicator::class)->settings($hotel);
        app(FinancialCommunicator::class)->updateSettings($hotel, [...$settings, 'enabled' => true, 'due_days_before' => 3]);
        $this->artisan('communications:financial-reminders')->assertSuccessful();
        $this->artisan('communications:financial-reminders')->assertSuccessful();
        $this->assertDatabaseCount('financial_communications', 1);
        $this->assertDatabaseHas('financial_communications', ['document_type' => 'reminder', 'recipient' => 'billing@company.test']);
    }
}
