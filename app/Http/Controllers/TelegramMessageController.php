<?php

namespace App\Http\Controllers;

use App\Models\TelegramMessage;

class TelegramMessageController extends Controller
{
    public function index()
    {
        // Only retrieve messages where is_public is true
        // $messages = TelegramMessage::where('is_public', true)->get();
        // return view('telegram_messages.index', ['messages' => $messages]);
    }
}
