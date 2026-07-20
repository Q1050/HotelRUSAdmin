<?php
namespace App\Contracts;use App\Models\{Folio,FolioPayment};
interface GuestPaymentProvider{public function charge(Folio$folio,int$amountMinor,string$currency,array$context=[]):array;public function refund(FolioPayment$payment,int$amountMinor,array$context=[]):array;}
