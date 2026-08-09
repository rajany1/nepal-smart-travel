<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeAll extends Command
{
    protected $signature = 'serve:all {--host=127.0.0.1} {--port=8000}';

    protected $description = 'Run the backend server + AI workers (queue + scheduler) with one command';

    public function handle(): int
    {
        $base = base_path();
        $php = PHP_BINARY;

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

        $this->info("AI workers running. Serving at http://{$this->option('host')}:{$this->option('port')} ...");
        $this->info('Press Ctrl+C to stop the server (workers keep running in their windows).');

        return $this->call('serve', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
        ]);
    }
}