<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestFolioController extends Controller
{
    public function show(Request $r, Reservation $reservation): JsonResponse
    {
        abort_unless($reservation->guest_id === $r->user()->id, 404);
        $folio = Folio::where('reservation_id', $reservation->id)->with(['items' => fn ($q) => $q->where('voided', false)->orderByDesc('service_date'), 'payments' => fn ($q) => $q->where('status', 'completed')->orderByDesc('processed_at')])->first();
        if (! $folio) {
            return response()->json(['data' => null]);
        }

return response()->json(['data' => ['number' => $folio->number, 'currency' => $folio->currency, 'status' => $folio->status, 'charges' => (float) $folio->charges_total, 'payments' => (float) $folio->payments_total, 'refunds' => (float) $folio->refunds_total, 'balance' => (float) $folio->balance, 'items' => $folio->items->map(fn ($i) => ['id' => $i->id, 'type' => $i->type, 'description' => $i->description, 'quantity' => (float) $i->quantity, 'unit_amount' => (float) $i->unit_amount, 'tax_amount' => (float) $i->tax_amount, 'total_amount' => (float) $i->total_amount, 'service_date' => $i->service_date->toDateString()]), 'payment_activity' => $folio->payments->map(fn ($p) => ['id' => $p->id, 'type' => $p->type, 'method' => $p->method, 'amount' => (float) $p->amount, 'processed_at' => $p->processed_at->toISOString()])]]);
    }
}
