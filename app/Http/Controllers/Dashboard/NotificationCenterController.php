<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\FinancialCommunication;
use App\Models\GuestNotificationPreference;
use App\Models\MobileNotification;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Notifications\NotificationRules;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterController extends Controller
{
    public function index(NotificationRules $rules): Response
    {
        $mobile = MobileNotification::with('guest')->latest()->limit(75)->get()->map(fn ($item) => ['id' => 'mobile-'.$item->id, 'channel' => implode(', ', $item->channels ?? ['fcm']), 'event' => $item->event_key ?? $item->category, 'recipient' => $item->guest ? trim($item->guest->first_name.' '.$item->guest->last_name) : 'Guest', 'title' => $item->title, 'status' => $item->delivery_status, 'scheduledAt' => $item->scheduled_for?->toIso8601String(), 'sentAt' => $item->sent_at?->toIso8601String(), 'readAt' => $item->read_at?->toIso8601String(), 'error' => $item->delivery_error]);
        $financial = FinancialCommunication::latest()->limit(75)->get()->map(fn ($item) => ['id' => 'financial-'.$item->id, 'channel' => 'email', 'event' => 'financial.'.$item->document_type, 'recipient' => $item->recipient, 'title' => $item->subject, 'status' => $item->opened_at ? 'opened' : $item->status, 'scheduledAt' => $item->scheduled_for?->toIso8601String(), 'sentAt' => $item->sent_at?->toIso8601String(), 'readAt' => $item->opened_at?->toIso8601String(), 'error' => $item->last_error]);
        $userIds = User::pluck('id');
        $dashboard = DB::table('notifications')->whereIn('notifiable_id', $userIds)->latest('created_at')->limit(75)->get()->map(function ($item) {
            $data = json_decode($item->data, true);

            return ['id' => 'dashboard-'.$item->id, 'channel' => 'dashboard', 'event' => class_basename($item->type), 'recipient' => 'Staff', 'title' => $data['message'] ?? 'Dashboard notification', 'status' => $item->read_at ? 'read' : 'delivered', 'scheduledAt' => $item->created_at, 'sentAt' => $item->created_at, 'readAt' => $item->read_at, 'error' => null];
        });
        $history = collect([...$mobile, ...$financial, ...$dashboard])->sortByDesc(fn ($item) => $item['scheduledAt'])->take(150)->values();
        $preferences = GuestNotificationPreference::whereHas('guest', fn ($query) => $query->where('hotel_id', app('currentHotel')->id));

        return Inertia::render('Notifications/Index', ['rules' => $rules->all(), 'history' => $history, 'stats' => ['total' => $history->count(), 'failed' => $history->whereIn('status', ['failed', 'retrying'])->count(), 'scheduled' => $history->whereIn('status', ['scheduled', 'queued'])->count(), 'opened' => $history->whereIn('status', ['opened', 'read'])->count()], 'guestPreferences' => ['profiles' => $preferences->count(), 'booking' => (clone $preferences)->where('booking_updates', true)->count(), 'access' => (clone $preferences)->where('access_updates', true)->count(), 'service' => (clone $preferences)->where('service_updates', true)->count(), 'checkout' => (clone $preferences)->where('checkout_reminders', true)->count(), 'marketing' => (clone $preferences)->where('marketing', true)->count()]]);
    }

    public function update(Request $request, NotificationRules $rules): RedirectResponse
    {
        $data = $request->validate(['rules' => ['required', 'array'], 'rules.*.event_key' => ['required', Rule::in(array_keys(NotificationRules::EVENTS))], 'rules.*.enabled' => ['required', 'boolean'], 'rules.*.channels' => ['required', 'array', 'min:1'], 'rules.*.channels.*' => [Rule::in(['dashboard', 'email', 'fcm'])], 'rules.*.recipient_roles' => ['array'], 'rules.*.recipient_roles.*' => [Rule::in(['super_admin', 'manager', 'front_desk', 'housekeeping', 'maintenance'])], 'rules.*.delivery_mode' => ['required', Rule::in(['immediate', 'digest'])], 'rules.*.digest_time' => ['nullable', 'date_format:H:i'], 'rules.*.quiet_start' => ['nullable', 'date_format:H:i'], 'rules.*.quiet_end' => ['nullable', 'date_format:H:i'], 'rules.*.escalation_minutes' => ['nullable', 'integer', 'between:1,10080'], 'rules.*.escalation_roles' => ['array'], 'rules.*.escalation_roles.*' => [Rule::in(['super_admin', 'manager', 'front_desk', 'housekeeping', 'maintenance'])], 'rules.*.subject_template' => ['nullable', 'string', 'max:200'], 'rules.*.body_template' => ['nullable', 'string', 'max:2000']]);
        foreach ($data['rules'] as $item) {
            $defaults = NotificationRules::EVENTS[$item['event_key']];
            NotificationRule::updateOrCreate(['event_key' => $item['event_key']], ['label' => $defaults['label'], ...$item]);
        }
        AuditLogger::record($request, 'notification_rules_updated', 'settings', 'sensitive', 'Unified notification rules updated.', app('currentHotel'));

        return back()->with('success', 'Notification rules saved.');
    }
}
