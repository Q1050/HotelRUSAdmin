<?php

namespace Tests\Feature;

use App\Models\{Hotel, Plan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_start_hosted_checkout(): void
    {
        config(['services.stripe.secret'=>'sk_test_example']);
        $admin=User::factory()->create(['is_platform_admin'=>true]);
        $hotel=Hotel::where('slug','default-hotel')->firstOrFail();
        $plan=Plan::where('key','operations')->firstOrFail();
        $plan->update(['stripe_price_id'=>'price_operations']);
        Http::fake(['api.stripe.com/*'=>Http::response(['url'=>'https://checkout.stripe.com/test-session'])]);

        $this->actingAs($admin)->post(route('platform.billing.checkout',$hotel),['plan_id'=>$plan->id])->assertRedirect('https://checkout.stripe.com/test-session');
        Http::assertSent(fn($request)=>$request->url()==='https://api.stripe.com/v1/checkout/sessions'&&$request['mode']==='subscription'&&$request['line_items'][0]['price']==='price_operations');
    }

    public function test_signed_checkout_webhook_updates_subscription_once(): void
    {
        config(['services.stripe.webhook_secret'=>'whsec_test']);
        $hotel=Hotel::where('slug','default-hotel')->firstOrFail();
        $plan=Plan::where('key','core')->firstOrFail();
        $event=['id'=>'evt_checkout_1','type'=>'checkout.session.completed','data'=>['object'=>['client_reference_id'=>(string)$hotel->id,'customer'=>'cus_123','subscription'=>'sub_123','metadata'=>['hotel_id'=>(string)$hotel->id,'plan_id'=>(string)$plan->id]]]];
        $payload=json_encode($event);$timestamp=time();
        $signature=hash_hmac('sha256',$timestamp.'.'.$payload,'whsec_test');
        $server=['HTTP_STRIPE_SIGNATURE'=>"t={$timestamp},v1={$signature}",'CONTENT_TYPE'=>'application/json'];

        $this->call('POST',route('webhooks.stripe'),[],[],[],$server,$payload)->assertOk();
        $this->call('POST',route('webhooks.stripe'),[],[],[],$server,$payload)->assertOk();
        $this->assertDatabaseHas('hotel_subscriptions',['hotel_id'=>$hotel->id,'plan_id'=>$plan->id,'provider'=>'stripe','provider_customer_id'=>'cus_123','provider_subscription_id'=>'sub_123','status'=>'active']);
        $this->assertDatabaseCount('billing_webhook_events',1);
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        config(['services.stripe.webhook_secret'=>'whsec_test']);
        $this->postJson(route('webhooks.stripe'),['id'=>'evt_bad'])->assertStatus(400);
    }
}
