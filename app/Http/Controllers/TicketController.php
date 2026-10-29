<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\SiteSetting;
use App\Models\TechnicalExpert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (SiteSetting::get('ticket_enabled', '0') !== '1') {
                abort(404);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $tickets = $request->user()
            ->tickets()
            ->withCount('replies')
            ->latest()
            ->paginate(15);

        return view('tickets.index', compact('tickets'));
    }

    public function create()
    {
        $experts = TechnicalExpert::active()->get();
        return view('tickets.create', compact('experts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject'  => 'required|string|max:255',
            'message'  => 'required|string|max:10000',
            'priority' => 'required|in:low,medium,high,urgent',
            'category' => 'required|in:general,billing,technical,account',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status']  = 'open';

        $ticket = Ticket::create($validated);

        // Auto-reply from system
        $autoReply = SiteSetting::get('ticket_auto_reply', '');
        if ($autoReply) {
            TicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id'   => null,
                'is_admin'  => true,
                'message'   => $autoReply,
            ]);
        }

        // Notify admin via email
        $this->notifyAdmin($ticket);

        return redirect()->route('tickets.show', $ticket)->with('success', __('ticket.created'));
    }

    public function show(Request $request, Ticket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->load(['replies.user']);

        return view('tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        if (!$ticket->isOpen()) {
            return back()->withErrors(['message' => __('ticket.closed_no_reply')]);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $request->user()->id,
            'is_admin'  => false,
            'message'   => $validated['message'],
        ]);

        // Re-open if it was resolved
        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return back()->with('success', __('ticket.reply_sent'));
    }

    public function close(Request $request, Ticket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return back()->with('success', __('ticket.closed'));
    }

    private function notifyAdmin(Ticket $ticket): void
    {
        $adminEmail = SiteSetting::get('contact_email');
        $siteName = __(SiteSetting::get('site_name', config('app.name', 'Platform')));
        $fromAddress = SiteSetting::get('mail_from_address', config('mail.from.address'));
        $fromName = __(SiteSetting::get('mail_from_name', config('mail.from.name', config('app.name', 'Platform'))));

        if (!$adminEmail) return;

        try {
            Mail::raw(
                "New support ticket #{$ticket->id}\n\n" .
                "From: {$ticket->user->name} ({$ticket->user->email})\n" .
                "Category: {$ticket->category} | Priority: {$ticket->priority}\n" .
                "Subject: {$ticket->subject}\n\n" .
                "---\n\n{$ticket->message}",
                function ($msg) use ($adminEmail, $ticket, $siteName, $fromAddress, $fromName) {
                    $msg->to($adminEmail)
                        ->subject("[{$siteName}] New Ticket #{$ticket->id}: {$ticket->subject}");
                    if ($fromAddress) {
                        $msg->from($fromAddress, $fromName);
                    }
                }
            );
        } catch (\Exception $e) {
            Log::warning('Ticket notification email failed: ' . $e->getMessage());
        }
    }
}
