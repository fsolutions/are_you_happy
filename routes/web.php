<?php

use App\Models\TelegramMessage;
use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Http\Controllers\TelegramBotController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::post('/telegram-webhook', [TelegramBotController::class, 'handleRequest']);

Route::get('/set-webhook', function() {
    $domain = env('TELEGRAM_WEBHOOK_DOMAIN');
    Telegram::setWebhook(['url' => $domain . '/telegram-webhook']);
    return 'Webhook set';
});

Route::get('/', function () {
    $messages = TelegramMessage::where('is_public', true)->get();
    
    foreach ($messages as $message) {
        // Replace double newlines with a temporary delimiter
        $tempContent = str_replace("\n\n", '##DOUBLEBREAK##', $message->text);

        // Split content by the temporary delimiter
        $paragraphs = explode('##DOUBLEBREAK##', $tempContent);

        // Process each paragraph
        $newContent = [];
        foreach ($paragraphs as $paragraph) {
            // Replace single newline with <br>
            $processedParagraph = str_replace("\n", '<br>', $paragraph);
            $newContent[] = '<p>' . trim($processedParagraph) . '</p>';
        }

        // Join the content back
        $message->text = implode("", $newContent);
    }
        
    return view('welcome', ['messages' => $messages]);
});
