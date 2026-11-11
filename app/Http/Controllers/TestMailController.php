<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestMailController extends Controller
{
    /**
     * Send a test e-mail with a dummy QR code to the logged-in user.
     */
    public function __invoke(Request $request, Event $event)
    {
        $user = $request->user();

        if ($event->roleFor($user) === null) {
            abort(403, 'Nemáte oprávnenie na správu tohto podujatia.');
        }

        try {
            $event->loadMissing('location');
            Mail::to($user->email)->send(new TestMail($event, $user));
        } catch (\Throwable $e) {
            Log::error('Failed to send test email: '.$e->getMessage());

            return back()->with('error', 'Testovací e-mail sa nepodarilo odoslať: '.$e->getMessage());
        }

        return back()->with('success', "Testovací e-mail bol odoslaný na {$user->email}.");
    }
}
