<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupTelegramImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-telegram-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directory = public_path('telegram_images/temp/');
        $files = glob($directory . '*'); 

        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24) { // 24 hours
                    unlink($file);
                }
            }
        }

        $this->info('Old images cleaned up.');
    }
}
