<?php

namespace Tests\Feature;

use App\Models\{Guest,PreArrivalSubmission,Reservation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacyRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_id_documents_are_purged_after_the_hotel_retention_period(): void
    {
        Storage::fake('local');
        $guest=Guest::create(['first_name'=>'Ana','last_name'=>'Stone','email'=>'privacy@example.com']);
        $hotel=$guest->hotel;$settings=$hotel->settings??[];$settings['branding']['id_document_retention_days']=7;$hotel->update(['settings'=>$settings]);
        $reservation=Reservation::create(['guest_id'=>$guest->id,'reference'=>'PRIVACY-1','arrival_date'=>today()->subDays(20),'departure_date'=>today()->subDays(15),'guest_count'=>1,'room_type'=>'King','status'=>'completed','payment_status'=>'paid']);
        Storage::disk('local')->put('ids/front.jpg','front');Storage::disk('local')->put('ids/back.jpg','back');
        $submission=PreArrivalSubmission::create(['reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'status'=>'approved','id_type'=>'passport','id_number'=>'P12345','id_document_front'=>'ids/front.jpg','id_document_back'=>'ids/back.jpg','policy_accepted'=>true,'consented_at'=>now()->subDays(20),'reviewed_at'=>now()->subDays(8)]);
        $this->artisan('privacy:purge-id-documents')->assertSuccessful();
        $submission->refresh();$this->assertNull($submission->id_document_front);$this->assertNull($submission->id_document_back);$this->assertNull($submission->id_number);Storage::disk('local')->assertMissing('ids/front.jpg');Storage::disk('local')->assertMissing('ids/back.jpg');
    }
}
