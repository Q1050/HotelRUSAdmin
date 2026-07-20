<?php

namespace Tests\Feature;

use App\Models\AccountingExportBatch;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\FolioPayment;
use App\Models\Guest;
use App\Models\NightAudit;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Folios\FolioLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingExportTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $guest = Guest::create(['first_name' => 'Ledger', 'last_name' => 'Guest']);
        $reservation = Reservation::create(['guest_id' => $guest->id, 'reference' => 'RS-GL', 'arrival_date' => today(), 'departure_date' => today()->addDay(), 'status' => 'confirmed']);
        $folio = Folio::forReservation($reservation);
        FolioItem::create(['folio_id' => $folio->id, 'type' => 'room_charge', 'description' => 'Room revenue', 'quantity' => 1, 'unit_amount' => 100, 'tax_amount' => 10, 'total_amount' => 110, 'service_date' => today()]);
        FolioPayment::create(['folio_id' => $folio->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 110, 'status' => 'completed', 'processed_at' => now(), 'recorded_by' => $manager->id]);
        app(FolioLedger::class)->recalculate($folio);
        $audit = NightAudit::create(['business_date' => today(), 'status' => 'closed', 'room_revenue' => 110, 'payments' => 110, 'closed_by' => $manager->id, 'closed_at' => now()]);

        return compact('manager', 'folio', 'audit');
    }

    public function test_manager_generates_balanced_csv_and_json_journals(): void
    {
        ['manager' => $manager, 'audit' => $audit] = $this->data();
        $this->actingAs($manager)->post(route('dashboard.accounting.generate', $audit))->assertSessionHasNoErrors();
        $batch = AccountingExportBatch::firstOrFail();
        $this->assertSame('validated', $batch->status);
        $this->assertSame('220.00', $batch->debit_total);
        $this->assertSame($batch->debit_total, $batch->credit_total);
        $this->assertDatabaseHas('accounting_export_entries', ['account_key' => 'room_revenue', 'credit' => 100]);
        $this->actingAs($manager)->get(route('dashboard.accounting.download', ['batch' => $batch, 'format' => 'csv']))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($manager)->get(route('dashboard.accounting.download', ['batch' => $batch, 'format' => 'json']))->assertOk()->assertHeader('content-type', 'application/json');
        $this->actingAs($manager)->get(route('dashboard.accounting.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Accounting/Index')->where('batches.0.number', $batch->batch_number));
    }

    public function test_posted_period_is_locked_until_reversal(): void
    {
        ['manager' => $manager, 'folio' => $folio, 'audit' => $audit] = $this->data();
        $this->actingAs($manager)->post(route('dashboard.accounting.generate', $audit));
        $batch = AccountingExportBatch::firstOrFail();
        $this->actingAs($manager)->patch(route('dashboard.accounting.post', $batch))->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('dashboard.folios.items.store', $folio), ['type' => 'service', 'description' => 'Late posting', 'quantity' => 1, 'unit_amount' => 10, 'tax_amount' => 0, 'service_date' => today()->toDateString()])->assertStatus(422);
        $this->actingAs($manager)->post(route('dashboard.accounting.reverse', $batch), ['reason' => 'Night audit reopened for correction'])->assertSessionHasNoErrors();
        $this->assertSame('reversed', $batch->fresh()->status);
        $this->assertDatabaseHas('accounting_export_batches', ['reversal_of_id' => $batch->id, 'status' => 'posted']);
        $this->actingAs($manager)->post(route('dashboard.folios.items.store', $folio), ['type' => 'service', 'description' => 'Corrected posting', 'quantity' => 1, 'unit_amount' => 10, 'tax_amount' => 0, 'service_date' => today()->toDateString()])->assertSessionHasNoErrors();
    }
}
