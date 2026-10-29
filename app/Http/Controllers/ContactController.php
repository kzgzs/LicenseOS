<?php

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        return view('contact');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255',
            'other_contact' => 'nullable|string|max:255',
            'message'       => 'required|string|max:5000',
        ]);

        $validated['user_id'] = $request->user()?->id;
        $validated['status']  = 'new';

        ContactSubmission::create($validated);

        // Send email notification to admin
        $adminEmail = SiteSetting::get('contact_email');
        $siteName = __(SiteSetting::get('site_name', config('app.name', 'Platform')));
        $fromAddress = SiteSetting::get('mail_from_address', config('mail.from.address'));
        $fromName = __(SiteSetting::get('mail_from_name', config('mail.from.name', config('app.name', 'Platform'))));

        if ($adminEmail) {
            try {
                Mail::raw(
                    "New message from {$validated['name']} ({$validated['email']})\n" .
                    ($validated['other_contact'] ? "Other Contact: {$validated['other_contact']}\n" : '') .
                    "\n---\n\n{$validated['message']}",
                    function ($msg) use ($adminEmail, $validated, $fromAddress, $fromName, $siteName) {
                        $msg->to($adminEmail)
                            ->replyTo($validated['email'], $validated['name'])
                            ->subject("[{$siteName}] New Contact Message");
                        if ($fromAddress) {
                            $msg->from($fromAddress, $fromName);
                        }
                    }
                );
            } catch (\Exception $e) {
                \Log::warning('Contact email failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', __('contact.success_sent'));
    }
}
