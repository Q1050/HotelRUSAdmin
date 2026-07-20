<?php

namespace Tests\Feature;

use App\Models\{Guest, PreArrivalSubmission, Reservation, ReservationClaimToken, User};
use App\Notifications\{PreArrivalReviewed, ReservationClaimCode};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Notification, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestPreArrivalTest extends TestCase
{
    use RefreshDatabase;
    private string $device='5d48e2b8-2432-4b28-a577-8926e1198a1e';

    public function test_matching_guest_can_request_a_claim_code(): void
    {
        Notification::fake();[$guest,$token]=$this->appGuest('traveler@example.com');$placeholder=Guest::create(['first_name'=>'Travel','last_name'=>'Guest','email'=>'traveler@example.com']);$reservation=$this->reservation($placeholder,'RS-CLAIM1');
        $this->auth($token)->postJson('/api/v1/reservations/claim/request',['reference'=>$reservation->reference])->assertOk();
        $this->assertDatabaseHas('reservation_claim_tokens',['reservation_id'=>$reservation->id,'guest_id'=>$guest->id]);Notification::assertSentTo($guest,ReservationClaimCode::class);
    }

    public function test_verified_claim_transfers_reservation_and_retains_merge_trail(): void
    {
        [$guest,$token]=$this->appGuest('traveler@example.com');$placeholder=Guest::create(['first_name'=>'Travel','last_name'=>'Guest','email'=>'traveler@example.com']);$reservation=$this->reservation($placeholder,'RS-CLAIM2');ReservationClaimToken::create(['reservation_id'=>$reservation->id,'guest_id'=>$guest->id,'code_hash'=>Hash::make('123456'),'expires_at'=>now()->addMinutes(15)]);
        $this->auth($token)->postJson('/api/v1/reservations/claim/verify',['reference'=>$reservation->reference,'code'=>'123456'])->assertOk();
        $this->assertSame($guest->id,$reservation->fresh()->guest_id);$this->assertSame('merged',$placeholder->fresh()->account_status);$this->assertSame($guest->id,$placeholder->fresh()->merged_into_guest_id);
    }

    public function test_guest_can_submit_private_documents_and_staff_can_approve(): void
    {
        Storage::fake('local');Notification::fake();[$guest,$token]=$this->appGuest('arrival@example.com');$reservation=$this->reservation($guest,'RS-ARRIVE');
        $this->auth($token)->post('/api/v1/reservations/'.$reservation->id.'/pre-arrival',['id_type'=>'passport','id_number'=>'P123456','id_document_front'=>UploadedFile::fake()->image('passport.jpg'),'estimated_arrival_time'=>'15:30','guest_notes'=>'Late flight','policy_accepted'=>'1'],['Accept'=>'application/json'])->assertCreated();
        $submission=PreArrivalSubmission::first();Storage::disk('local')->assertExists($submission->id_document_front);$this->assertSame('pending',$submission->status);
        $manager=User::factory()->create(['role'=>'manager']);$this->actingAs($manager)->get(route('dashboard.pre-arrivals.document',[$submission,'front']))->assertOk()->assertHeader('cache-control','no-store, private');
        $this->actingAs($manager)->patch(route('dashboard.pre-arrivals.review',$submission),['decision'=>'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved',$submission->fresh()->status);$this->assertSame('verified',$guest->fresh()->id_status);Notification::assertSentTo($guest,PreArrivalReviewed::class);
    }

    public function test_guest_cannot_read_another_guests_pre_arrival_submission(): void
    {
        $owner=Guest::create(['first_name'=>'Owner','last_name'=>'Guest','email'=>'owner@example.com']);$reservation=$this->reservation($owner,'RS-PRIVATE');PreArrivalSubmission::create(['reservation_id'=>$reservation->id,'guest_id'=>$owner->id,'status'=>'pending','id_type'=>'passport','id_number'=>'P1','id_document_front'=>'private.jpg','policy_accepted'=>true,'consented_at'=>now()]);[, $otherToken]=$this->appGuest('other@example.com');
        $this->auth($otherToken)->getJson('/api/v1/reservations/'.$reservation->id.'/pre-arrival')->assertNotFound();
    }

    private function appGuest(string $email): array {$guest=Guest::create(['first_name'=>'App','last_name'=>'Guest','email'=>$email,'phone'=>'+1 555 0100','password'=>'secret123','account_status'=>'active']);$guest->devices()->create(['device_id'=>$this->device]);$token=$guest->createToken("guest-mobile:{$this->device}",['guest:mobile'],now()->addHour())->plainTextToken;return[$guest,$token];}
    private function reservation(Guest $guest,string $reference): Reservation{return Reservation::create(['guest_id'=>$guest->id,'reference'=>$reference,'arrival_date'=>today()->addDay(),'departure_date'=>today()->addDays(3),'guest_count'=>1,'status'=>'confirmed','payment_status'=>'pending','source'=>'online']);}
    private function auth(string $token): static{return $this->withToken($token)->withHeader('X-Device-ID',$this->device);}
}
