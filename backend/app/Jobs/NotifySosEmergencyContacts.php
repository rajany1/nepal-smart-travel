<?php

namespace App\Jobs;

use App\Models\EmergencyContact;
use App\Models\SosAlert;
use App\Services\PushNotificationService;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifySosEmergencyContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public SosAlert $sosAlert,
    ) {
    }

    public function handle(): void
    {
        try {
            $sos = $this->sosAlert;
            $user = $sos->user;

            $contacts = EmergencyContact::where('user_id', $sos->user_id)
                ->where('is_active', true)
                ->get();

            $emergencyTypeLabels = [
                'medical' => 'Medical Emergency',
                'accident' => 'Accident',
                'flood' => 'Flood',
                'other' => 'Emergency',
            ];
            $typeLabel = $emergencyTypeLabels[$sos->emergency_type] ?? 'Emergency';

            $locationUrl = "https://maps.google.com/?q={$sos->latitude},{$sos->longitude}";
            $pushCount = 0;
            $smsCount = 0;

            foreach ($contacts as $contact) {
                if ($contact->contact_user_id) {
                    // App user cha → Push notification
                    $body = "{$user->name} needs help. {$typeLabel} at their location. Tap to view live location.";
                    if ($sos->message) {
                        $body .= " Message: {$sos->message}";
                    }

                    PushNotificationService::sendToUser(
                        $contact->contact_user_id,
                        'SOS Emergency',
                        $body,
                        [
                            'type' => 'sos_emergency',
                            'sos_id' => (string) $sos->id,
                            'user_id' => (string) $user->id,
                            'user_name' => $user->name,
                            'latitude' => (string) $sos->latitude,
                            'longitude' => (string) $sos->longitude,
                            'emergency_type' => $sos->emergency_type,
                            'message' => $sos->message ?? '',
                            'started_at' => $sos->started_at->toIso8601String(),
                        ]
                    );
                    $pushCount++;
                } elseif (!empty($contact->phone_number)) {
                    // App xaina → SMS fallback
                    $smsBody = "🚨 SOS Alert from {$user->name}!\n";
                    $smsBody .= "{$typeLabel}\n";
                    $smsBody .= "Location: {$locationUrl}\n";
                    $smsBody .= "Tap to view live location.";
                    if ($sos->message) {
                        $smsBody .= "\nMessage: {$sos->message}";
                    }

                    SmsService::send($contact->phone_number, $smsBody);
                    $smsCount++;
                }
            }

            Log::info("SOS notifications sent for user {$user->id}: {$pushCount} push, {$smsCount} SMS");
        } catch (\Throwable $e) {
            Log::error('SOS emergency contact notification failed: ' . $e->getMessage());
        }
    }
}
