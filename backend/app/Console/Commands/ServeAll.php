<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAll extends Command
{
    protected $signature = 'serve:all {--host=127.0.0.1} {--port=8000}';

    protected $description = 'Run Redis + backend server + queue + scheduler with one command';

    public function handle(): int
    {
        $base = base_path();
        $php = PHP_BINARY;

        // --- Redis ---
        $this->startRedis();

        if (DIRECTORY_SEPARATOR === '\\') {
            $jobs = [
                'AI-Queue' => "start \"AI-Queue\" /min cmd /c \"cd /d {$base} && {$php} artisan queue:work database --tries=3 --timeout=300 --sleep=1\"",
                'AI-Scheduler' => "start \"AI-Scheduler\" /min cmd /c \"cd /d {$base} && {$php} artisan schedule:work\"",
            ];
            foreach ($jobs as $name => $cmd) {
                pclose(popen($cmd, 'r'));
                $this->info("Started {$name} worker.");
            }
        } else {
            $jobs = [
                'queue' => ['nohup', $php, 'artisan', 'queue:work', 'database', '--tries=3', '--timeout=300', '--sleep=1'],
                'scheduler' => ['nohup', $php, 'artisan', 'schedule:work'],
            ];
            foreach ($jobs as $name => $job) {
                $process = new Process($job, $base);
                $process->setTimeout(null);
                $process->start();
                $this->info("Started {$name} worker.");
            }
        }

        $this->info("All services running. Serving at http://{$this->option('host')}:{$this->option('port')} ...");
        $this->info('Press Ctrl+C to stop all services.');

        return $this->call('serve', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
        ]);
    }

    private function startRedis(): void
    {
        $container = 'nepal-redis';

        // Check if already running
        $check = trim(shell_exec("docker inspect -f '{{.State.Running}}' {$container} 2>nul") ?: '');
        if ($check === 'true') {
            $this->info("Redis ({$container}) already running.");
            return;
        }

        // Remove stale container if exists but stopped
        shell_exec("docker rm -f {$container} 2>nul");

        $cmd = "docker run -d --name {$container} -p 6379:6379 redis:alpine redis-server --appendonly yes --maxmemory 128mb --maxmemory-policy allkeys-lru";
        exec($cmd, $output, $exitCode);

        if ($exitCode === 0) {
            // Wait for Redis to be ready
            sleep(2);
            $ping = trim(shell_exec("docker exec {$container} redis-cli ping 2>nul") ?: '');
            if ($ping === 'PONG') {
                $this->info("Redis started on port 6379 (PONG).");
            } else {
                $this->warn("Redis container started but not responding yet — will be ready shortly.");
            }
        } else {
            $this->error("Failed to start Redis. Is Docker running?");
        }
    }
}