<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CorporateInvoice extends Model
{
    use Concerns\BelongsToHotel;

    protected $fillable = ['hotel_id', 'corporate_account_id', 'group_booking_id', 'folio_id', 'number', 'status', 'currency', 'issue_date', 'due_date', 'subtotal', 'tax_total', 'total', 'paid_total', 'balance', 'notes', 'issued_by', 'issued_at', 'voided_by', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'paid_total' => 'decimal:2', 'balance' => 'decimal:2', 'issued_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function account()
    {
        return $this->belongsTo(CorporateAccount::class, 'corporate_account_id');
    }

    public function group()
    {
        return $this->belongsTo(GroupBooking::class, 'group_booking_id');
    }

    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }

    public function items()
    {
        return $this->hasMany(CorporateInvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(CorporateInvoicePayment::class);
    }

    public static function nextNumber(): string
    {
        return 'INV-'.now()->format('Ym').'-'.strtoupper(Str::random(6));
    }
}
