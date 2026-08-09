<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Manager AI',
                'agent_type' => 'manager',
                'description' => 'Big Boss — delegates tasks and assesses workload',
                'status' => 'idle',
                'model' => 'gemini-2.0-flash',
                'provider' => 'gemini',
                'capabilities' => ['assess', 'delegate', 'report'],
            ],
            [
                'name' => 'Translation AI',
                'agent_type' => 'translation',
                'description' => 'Translates content to Nepali',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['translate', 'auto-translate'],
            ],
            [
                'name' => 'Review Moderator',
                'agent_type' => 'review_moderator',
                'description' => 'Moderates place reviews for spam and toxicity',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['moderate', 'auto-moderate'],
            ],
            [
                'name' => 'Report Manager',
                'agent_type' => 'report_manager',
                'description' => 'Analyzes, approves/rejects reports with AI',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['analyze', 'process-pending', 'approve', 'reject'],
            ],
            [
                'name' => 'Travel Consultant AI',
                'agent_type' => 'travel_consultant',
                'description' => 'Builds multi-day itineraries from top-rated places per district',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['itinerary', 'auto-work'],
            ],
            [
                'name' => 'Hotel Manager AI',
                'agent_type' => 'hotel_manager',
                'description' => 'Analyzes hotel performance from reviews and ratings',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['analyze', 'auto-work'],
            ],
            [
                'name' => 'Content Writer AI',
                'agent_type' => 'content_writer',
                'description' => 'Writes Nepali descriptions for places missing content',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['write', 'auto-work'],
            ],
            [
                'name' => 'Weather Analyst AI',
                'agent_type' => 'weather_analyst',
                'description' => 'Checks live weather grid data and issues travel advisories',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['forecast', 'advisory', 'auto-work'],
            ],
            [
                'name' => 'Route Planner AI',
                'agent_type' => 'route_planner',
                'description' => 'Computes driving routes between top destinations via OSRM',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['route', 'auto-work'],
            ],
            [
                'name' => 'Booking Assistant AI',
                'agent_type' => 'booking_assistant',
                'description' => 'Daily booking digest, pending flags and follow-up drafts',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['digest', 'follow-up', 'auto-work'],
            ],
            [
                'name' => 'Fraud Detection AI',
                'agent_type' => 'fraud_detection',
                'description' => 'Scans reports, reviews and bookings for fraud signals',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['scan', 'gps-verify', 'auto-work'],
            ],
            [
                'name' => 'Marketing AI',
                'agent_type' => 'marketing',
                'description' => 'Generates promotional copy and campaign ideas for places',
                'status' => 'idle',
                'model' => 'llama-3.3-70b-versatile',
                'provider' => 'groq',
                'capabilities' => ['campaign', 'digest', 'auto-work'],
            ],
        ];

        foreach ($agents as $agent) {
            AiAgent::updateOrCreate(
                ['agent_type' => $agent['agent_type']],
                $agent
            );
        }
    }
}
