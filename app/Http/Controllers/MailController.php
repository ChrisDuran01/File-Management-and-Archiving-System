<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
public function sendMessage(Request $request)
{
    $request->validate([
        'name'       => 'required|string|max:255',
        'email'      => 'required|email',
        'student_id' => 'required|string|max:100',
        'subject'    => 'required|string|max:255',
        'message'    => 'required|string',
    ]);

    $receiver = env('MAIL_FROM_ADDRESS');

    $body  = "Full Name: {$request->name}\n";
    $body .= "Email Address: {$request->email}\n";
    $body .= "Student ID: {$request->student_id}\n\n";
    $body .= "Message:\n{$request->message}";

    Mail::raw($body, function ($mail) use ($request, $receiver) {
        $mail->to($receiver)
             ->subject($request->subject)
             ->replyTo($request->email, $request->name);
    });

    return back()->with('success', 'Message sent to QSU Student Government');
}
}
