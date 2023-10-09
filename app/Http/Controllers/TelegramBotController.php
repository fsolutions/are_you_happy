<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramBotController extends Controller
{
    public function handleRequest()
    {
        $botToken = env('TELEGRAM_BOT_TOKEN');
    
        $updates = Telegram::getWebhookUpdates();
        Log::info(json_encode($updates));
    
        $isCallback = isset($updates->callback_query);
        $user_id = $isCallback ? $updates->callback_query->from->id : $updates->message->from->id;
        $callbackData = $isCallback ? $updates->callback_query->data : null;

        if ($isCallback) {
            $this->handleCallbackQuery($user_id, $callbackData);
            return response()->json([], 200);
        }
    
        $text = $updates->message->text ?? $updates->message->caption ?? null;
        $photos = $updates->message->photo;

        $saved_image_paths = [];
    
        // Handling incoming photos
        if ($photos) {
            $lastBiggestFile = null;

            foreach ($photos as $photo) {
                if ($lastBiggestFile == null) {
                    $lastBiggestFile = $photo;                    
                }

                if ($lastBiggestFile->file_size < $photo->file_size) {
                    $lastBiggestFile = $photo;
                }
            }

            $fileId = $lastBiggestFile->file_id;
            $file = Telegram::getFile(['file_id' => $fileId]);
            $filePath = $file->filePath;
            $imageContents = file_get_contents('https://api.telegram.org/file/bot' . $botToken . '/' . $filePath);
            $imageExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $localImageName = time() . "_" . $fileId . "." . $imageExtension;
            $localImagePath = public_path('telegram_images/temp/' . $localImageName);
            file_put_contents($localImagePath, $imageContents);
            
            $saved_image_paths[] = 'telegram_images/temp/' . $localImageName;

            $cached_photos = Cache::get('pending_photos_' . $user_id, []);
            $cached_photos = array_merge($cached_photos, $saved_image_paths);
            Cache::put('pending_photos_' . $user_id, $cached_photos, 600);
        }
    
        // Ask the user the question and cache the message/images ONLY if there's either text or photos
        if ($text) {
            $cached_photos = Cache::get('pending_photos_' . $user_id, []);
            $all_images = array_merge($saved_image_paths, $cached_photos);
            
            Cache::put('awaiting_public_response_' . $user_id, true, 600); 
            $contentWithBreaks = str_replace("\n", "<br>", $text);
            Cache::put('temp_message_data_' . $user_id, ['text' => $contentWithBreaks, 'image_paths' => $all_images], 600);
        
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Yes', 'callback_data' => 'yes'],
                        ['text' => 'No', 'callback_data' => 'no'],
                        ['text' => 'Don`t save', 'callback_data' => 'dntsave']
                    ]
                ]
            ];
        
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Open your message for the public?',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            // For debugging: Log if no text or photo was detected
            Log::info('No text or photo detected for user ' . $user_id);
        }
    
        return response()->json([], 200);
    }
    
    private function handleCallbackQuery($user_id, $callbackData)
    {
        Cache::forget('awaiting_public_response_' . $user_id);
        $cachedData = Cache::get('temp_message_data_' . $user_id);

        if (!$cachedData) {
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Invalid action or session has expired.'
            ]);
            return;
        }

        $is_public = false;
        $dntsave = false;

        if ($callbackData === 'yes') {
            $is_public = true;
            
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Your message is now public.'
            ]);
        } elseif ($callbackData === 'no') {
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Your message will remain private.'
            ]);
        } elseif ($callbackData === 'dntsave') {
            $dntsave = true;
            
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Your message has been deleted.'
            ]);
        } else {
            $dntsave = true;
            
            Telegram::sendMessage([
                'chat_id' => $user_id,
                'text' => 'Invalid response. Your temporary message has been deleted.'
            ]);
            return;
        }

        if (!$dntsave) {
            // Move accepted images from 'temp' to main folder
            foreach ($cachedData['image_paths'] as &$imagePath) { // Notice the & before $imagePath for reference
                $oldPath = public_path($imagePath); // This is the temp path
                $newPath = str_replace('/temp/', '/', $oldPath); // This will be the main directory path
                if (file_exists($oldPath)) {
                    rename($oldPath, $newPath); // Move the file
                    $imagePath = str_replace('/temp/', '/', $imagePath); // Update the image path
                }
            }            
        
            // Update the image paths in the cached data to point to the main directory
            $cachedData['image_paths'] = str_replace('/temp/', '/', $cachedData['image_paths']);
                    
            DB::table('telegram_messages')->insert([
                'user_id' => $user_id,
                'text' => $cachedData['text'],
                'image_paths' => json_encode($cachedData['image_paths']),
                'is_public' => $is_public
            ]);
        }

        Cache::forget('temp_message_data_' . $user_id);
        Cache::forget('pending_photos_' . $user_id);
    }
}
