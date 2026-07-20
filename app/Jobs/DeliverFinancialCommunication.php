<?php

namespace App\Jobs;

use App\Models\FinancialCommunication;
use App\Services\Finance\FinancialCommunicator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverFinancialCommunication implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('notifications');
    }

    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(FinancialCommunicator $communicator): void
    {
        $delivery = FinancialCommunication::withoutGlobalScopes()->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') {
            return;
        }
        $delivery->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'last_attempt_at' => now(), 'last_error' => null]);
        $document = $communicator->document($delivery);
        $settings = $communicator->settings($document['hotel']);
        Mail::html($communicator->html($delivery, $document['hotel']), function ($message) use ($delivery, $document, $settings) {
            $message->to($delivery->recipient)->subject($delivery->subject)->attachData($document['pdf'], $document['name'], ['mime' => 'application/pdf']);
            if (filled($settings['reply_to'])) {
                $message->replyTo($settings['reply_to']);
            }
        });
        $delivery->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        FinancialCommunication::withoutGlobalScopes()->find($this->deliveryId)?->update(['status' => 'failed', 'last_error' => substr($exception->getMessage(), 0, 2000)]);
    }
}
