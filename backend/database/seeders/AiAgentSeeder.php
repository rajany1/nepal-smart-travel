<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        AiAgent::create([
            'name' => 'Manager AI',
            'agent_type' => 'manager',
            'description' => 'Big Boss — delegates tasks and assesses workload',
            'status' => 'idle',
            'model' => 'gemini-2.0-flash',
            'provider' => 'gemini',
            'capabilities' => ['assess', 'delegate', 'report'],
        ]);

        AiAgent::create([
            'name' => 'Translation AI',
            'agent_type' => 'translation',
            'description' => 'Translates content to Nepali',
            'status' => 'idle',
            'model' => 'llama-3.3-70b-versatile',
            'provider' => 'groq',
            'capabilities' => ['translate', 'auto-translate'],
        ]);

        AiAgent::create([
            'name' => 'Review Moderator',
            'agent_type' => 'review_moderator',
            'description' => 'Moderates place reviews for spam and toxicity',
            'status' => 'idle',
            'model' => 'llama-3.3-70b-versatile',
            'provider' => 'groq',
            'capabilities' => ['moderate', 'auto-moderate'],
        ]);

        AiAgent::create([
            'name' => 'Report Manager',
            'agent_type' => 'report_manager',
            'description' => 'Analyzes, approves/rejects reports with AI',
            'status' => 'idle',
            'model' => 'llama-3.3-70b-versatile',
            'provider' => 'groq',
            'capabilities' => ['analyze', 'process-pending', 'approve', 'reject'],
        ]);
    }
}
