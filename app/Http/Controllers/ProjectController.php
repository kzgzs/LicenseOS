<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBid;
use App\Models\ProjectContract;
use App\Models\ProjectMilestone;
use App\Models\ProjectEscrow;
use App\Models\ProjectDispute;
use App\Models\ProjectTimeLog;
use App\Models\ProjectMessage;
use App\Models\ProjectReview;
use App\Models\ProjectTemplate;
use App\Models\DeveloperBadge;
use App\Models\DeveloperSkill;
use App\Models\DeveloperScore;
use App\Models\Review;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TechnicalExpert;
use App\Services\CreditScoreService;
use App\Services\ProjectMatchingService;
use App\Services\StripeService;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (SiteSetting::get('freelance_enabled', '0') !== '1') {
                abort(404);
            }
            return $next($request);
        });
    }

    // ─── Public: Browse Projects ───

    public function index(Request $request)
    {
        $query = Project::with('client')->withCount('bids');

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Budget range filter
        if ($min = $request->input('budget_min')) {
            $query->where('budget_max', '>=', $min);
        }
        if ($max = $request->input('budget_max')) {
            $query->where('budget_min', '<=', $max);
        }

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter (default: open)
        $status = $request->input('status', 'open');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $projects = $query->latest()->paginate(12)->withQueryString();
        $categories = Project::categories();

        return view('projects.index', compact('projects', 'categories', 'status'));
    }

    // ─── Public: Project Detail ───

    public function show(Project $project)
    {
        $project->load(['client', 'bids.developer']);
        $contract = $project->contract;

        // Calculate developer ratings for each bidder
        $bidderRatings = [];
        foreach ($project->bids as $bid) {
            $devId = $bid->developer_id;
            $avgRating = Review::where('user_id', $devId)->avg('rating');
            $reviewCount = Review::where('user_id', $devId)->count();
            $bidderRatings[$devId] = [
                'avg' => round($avgRating ?? 0, 1),
                'count' => $reviewCount,
            ];
        }

        // Check if current user has already bid
        $myBid = null;
        if (Auth::check()) {
            $myBid = $project->bids->where('developer_id', Auth::id())->first();
        }

        // Recommended technical experts (matching project category)
        $experts = TechnicalExpert::active()
            ->when($project->category, fn ($q) => $q->where('specialty', 'like', '%' . $project->category . '%'))
            ->limit(4)
            ->get();

        return view('projects.show', compact('project', 'contract', 'myBid', 'bidderRatings', 'experts'));
    }

    // ─── Auth: Create Project Form ───

    public function create()
    {
        $this->middleware('auth');
        $categories = Project::categories();
        $durations = Project::durations();

        return view('projects.create', compact('categories', 'durations'));
    }

    // ─── Auth: Store Project ───

    public function store(Request $request)
    {
        $this->middleware('auth');

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string|max:10000',
            'category' => 'required|in:' . implode(',', array_keys(Project::CATEGORIES)),
            'skills' => 'nullable|string|max:500',
            'budget_min' => 'nullable|numeric|min:0|max:999999',
            'budget_max' => 'nullable|numeric|min:0|max:999999|gte:budget_min',
            'duration' => 'nullable|in:1-3days,1-2weeks,2-4weeks,1-3months,3-6months,6months+',
            'mode' => 'nullable|in:bidding,bounty,assignment',
        ]);

        // Parse skills from comma-separated string to array
        $skills = null;
        if (!empty($validated['skills'])) {
            $skills = array_map('trim', explode(',', $validated['skills']));
        }

        $project = Project::create([
            'client_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'skills' => $skills,
            'budget_min' => $validated['budget_min'] ?? null,
            'budget_max' => $validated['budget_max'] ?? null,
            'currency' => SiteSetting::get('store_currency', 'USD'),
            'duration' => $validated['duration'] ?? null,
            'status' => 'open',
            'mode' => $validated['mode'] ?? 'bidding',
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', __('project.created_success'));
    }

    // ─── Auth: Place a Bid ───

    public function bid(Request $request, Project $project)
    {
        $this->middleware('auth');

        // Only developers can bid
        if (!$request->user()->isDeveloper()) {
            return back()->withErrors(['message' => __('project.developer_only_bid')]);
        }

        // Can't bid on own project
        if ($project->client_id === $request->user()->id) {
            return back()->withErrors(['message' => __('project.cannot_bid_own')]);
        }

        if (!$project->isOpen()) {
            return back()->withErrors(['message' => __('project.not_open')]);
        }

        // Check for existing bid
        $existing = ProjectBid::where('project_id', $project->id)
            ->where('developer_id', $request->user()->id)
            ->first();

        if ($existing) {
            return back()->withErrors(['message' => __('project.already_bid')]);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:999999',
            'proposal' => 'required|string|max:5000',
            'delivery_days' => 'nullable|integer|min:1|max:365',
        ]);

        ProjectBid::create([
            'project_id' => $project->id,
            'developer_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'currency' => $project->currency,
            'proposal' => $validated['proposal'],
            'delivery_days' => $validated['delivery_days'] ?? null,
            'status' => 'pending',
        ]);

        // Increment bid count
        $project->increment('bid_count');

        // Notify client
        $this->notifyUser($project->client_id, "project.new_bid", [
            'title' => $project->title,
            'developer' => $request->user()->name,
            'amount' => $validated['amount'],
        ]);

        return back()->with('success', __('project.bid_placed'));
    }

    // ─── Auth: Client Accepts a Bid ───

    public function acceptBid(Request $request, ProjectBid $bid)
    {
        $this->middleware('auth');

        $project = $bid->project;

        // Only the project client can accept
        if ($project->client_id !== $request->user()->id) {
            abort(403);
        }

        if (!$project->isOpen()) {
            return back()->withErrors(['message' => __('project.not_open')]);
        }

        DB::transaction(function () use ($project, $bid) {
            // Mark this bid as accepted, others as rejected
            $project->bids()->where('id', '!=', $bid->id)->update(['status' => 'rejected']);
            $bid->update(['status' => 'accepted']);

            // Create contract
            $rate = (float) SiteSetting::get('freelance_commission_rate', '10');
            $fee = round((float) $bid->amount * ($rate / 100), 2);

            $contract = ProjectContract::create([
                'project_id' => $project->id,
                'client_id' => $project->client_id,
                'developer_id' => $bid->developer_id,
                'bid_id' => $bid->id,
                'total_amount' => $bid->amount,
                'currency' => $bid->currency,
                'platform_fee' => $fee,
                'developer_income' => (float) $bid->amount - $fee,
                'status' => 'active',
                'started_at' => now(),
            ]);

            // Create Order record for payment tracking
            Order::create([
                'user_id' => $project->client_id,
                'tenant_id' => $bid->developer->tenant_id ?? null,
                'product_id' => null,
                'amount' => $bid->amount,
                'currency' => $bid->currency,
                'status' => 'pending',
                'payment_method' => 'escrow',
                'gateway' => 'platform',
                'tier_label' => 'freelance:' . $project->id,
                'domain' => $project->title,
            ]);

            // Create single milestone for MVP (full amount)
            ProjectMilestone::create([
                'contract_id' => $contract->id,
                'title' => __('project.full_delivery'),
                'description' => $project->title,
                'amount' => $bid->amount,
                'status' => 'pending',
            ]);

            // Update project status
            $project->update(['status' => 'in_progress']);
        });

        // Notify developer
        $this->notifyUser($bid->developer_id, "project.bid_accepted", [
            'title' => $project->title,
            'amount' => $bid->amount,
        ]);

        return redirect()->route('projects.contracts.show', $project)
            ->with('success', __('project.bid_accepted_success'));
    }

    // ─── Auth: Client Funds Milestone ───

    public function fundMilestone(Request $request, ProjectMilestone $milestone)
    {
        $this->middleware('auth');

        $contract = $milestone->contract;
        if ($contract->client_id !== $request->user()->id) {
            abort(403);
        }

        if ($milestone->status !== 'pending') {
            return back()->withErrors(['message' => __('project.milestone_not_pending')]);
        }

        $gateway = 'platform';
        $paymentIntentId = null;

        // Try Stripe PaymentIntent (capture: manual = escrow hold)
        $stripe = app(StripeService::class);
        if ($stripe->isConfigured()) {
            try {
                $intent = $stripe->createEscrowPaymentIntent($milestone->amount, $contract->currency);
                $paymentIntentId = $intent->id;
                $gateway = 'stripe';
            } catch (\Exception $e) {
                Log::warning('Stripe escrow failed, falling back to platform: ' . $e->getMessage());
            }
        }

        // Create escrow record (funded)
        ProjectEscrow::create([
            'contract_id' => $contract->id,
            'milestone_id' => $milestone->id,
            'amount' => $milestone->amount,
            'status' => 'funded',
            'gateway' => $gateway,
            'payment_intent_id' => $paymentIntentId,
            'funded_at' => now(),
        ]);

        $milestone->update([
            'status' => 'funded',
            'funded_at' => now(),
        ]);

        // Update associated Order status
        $order = Order::where('tier_label', 'freelance:' . $contract->project_id)
            ->where('user_id', $contract->client_id)
            ->first();
        if ($order && $order->status === 'pending') {
            $order->update([
                'status' => 'paid',
                'payment_intent_id' => $paymentIntentId,
                'payment_method' => $gateway,
            ]);
        }

        // Notify developer
        $this->notifyUser($contract->developer_id, "project.milestone_funded", [
            'title' => $milestone->title,
            'amount' => $milestone->amount,
        ]);

        return back()->with('success', __('project.milestone_funded_success'));
    }

    // ─── Auth: Developer Submits Deliverable ───

    public function submitDeliverable(Request $request, ProjectMilestone $milestone)
    {
        $this->middleware('auth');

        $contract = $milestone->contract;
        if ($contract->developer_id !== $request->user()->id) {
            abort(403);
        }

        if ($milestone->status !== 'funded') {
            return back()->withErrors(['message' => __('project.milestone_not_funded')]);
        }

        $validated = $request->validate([
            'deliverable_note' => 'required|string|max:5000',
            'deliverable_files' => 'nullable|array|max:5',
            'deliverable_files.*' => 'file|max:51200', // 50MB each
        ]);

        // Handle file uploads
        $uploadedFiles = [];
        if ($request->hasFile('deliverable_files')) {
            foreach ($request->file('deliverable_files') as $file) {
                $path = $file->store('deliverables/' . $milestone->id, 'public');
                $uploadedFiles[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // Auto-approve countdown: 14 days from submission
        $reviewDays = (int) SiteSetting::get('freelance_review_days', '14');
        $autoApproveAt = now()->addDays($reviewDays);

        $milestone->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'auto_approve_at' => $autoApproveAt,
            'deliverable_note' => $validated['deliverable_note'],
            'deliverable_files' => !empty($uploadedFiles) ? $uploadedFiles : null,
        ]);

        // Notify client
        $this->notifyUser($contract->client_id, "project.delivery_submitted", [
            'title' => $milestone->title,
        ]);

        return back()->with('success', __('project.delivery_submitted_success'));
    }

    // ─── Auth: Client Approves Milestone ───

    public function approveMilestone(Request $request, ProjectMilestone $milestone)
    {
        $this->middleware('auth');

        $contract = $milestone->contract;
        if ($contract->client_id !== $request->user()->id) {
            abort(403);
        }

        if ($milestone->status !== 'submitted') {
            return back()->withErrors(['message' => __('project.milestone_not_submitted')]);
        }

        $milestone->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // Auto-release funds
        $escrow = $milestone->escrow;
        if ($escrow && $escrow->isFunded()) {
            $escrow->releaseToDeveloper();
        }

        // Check if all milestones are released
        $allReleased = $contract->milestones()->whereNotIn('status', ['released'])->count() === 0;
        if ($allReleased) {
            $contract->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            $contract->project->update(['status' => 'completed']);

            // Send review invitation emails to both parties
            try {
                $client = $contract->client;
                $developer = $contract->developer;

                if ($client && $client->email) {
                    \Illuminate\Support\Facades\Mail::to($client->email)->queue(
                        new \App\Mail\ReviewInvitationMail($contract, $client->name, $developer->name)
                    );
                }
                if ($developer && $developer->email) {
                    \Illuminate\Support\Facades\Mail::to($developer->email)->queue(
                        new \App\Mail\ReviewInvitationMail($contract, $developer->name, $client->name)
                    );
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send review invitation: ' . $e->getMessage());
            }
        }

        // Notify developer
        $this->notifyUser($contract->developer_id, "project.milestone_approved", [
            'title' => $milestone->title,
            'amount' => $milestone->amount,
        ]);

        return back()->with('success', __('project.milestone_approved_success'));
    }

    // ─── Auth: Client Requests Revision ───

    public function requestRevision(Request $request, ProjectMilestone $milestone)
    {
        $this->middleware('auth');

        $contract = $milestone->contract;
        if ($contract->client_id !== $request->user()->id) {
            abort(403);
        }

        if ($milestone->status !== 'submitted') {
            return back()->withErrors(['message' => __('project.milestone_not_submitted')]);
        }

        $validated = $request->validate([
            'revision_note' => 'required|string|max:3000',
        ]);

        $milestone->update([
            'status' => 'funded', // Back to funded, developer can re-submit
            'submitted_at' => null,
            'deliverable_note' => null,
            'revision_note' => $validated['revision_note'],
        ]);

        // Notify developer
        $this->notifyUser($contract->developer_id, "project.revision_requested", [
            'title' => $milestone->title,
            'note' => $validated['revision_note'],
        ]);

        return back()->with('success', __('project.revision_requested_success'));
    }

    // ─── Auth: Cancel Project ───

    public function cancelProject(Request $request, Project $project)
    {
        $this->middleware('auth');

        if ($project->client_id !== $request->user()->id) {
            abort(403);
        }

        if (!$project->isOpen()) {
            return back()->withErrors(['message' => __('project.cannot_cancel')]);
        }

        $project->update(['status' => 'cancelled']);

        return redirect()->route('projects.index')
            ->with('success', __('project.cancelled_success'));
    }

    // ─── Auth: My Contracts ───

    public function myContracts(Request $request)
    {
        $this->middleware('auth');

        $contracts = ProjectContract::with(['project', 'milestones'])
            ->where(function ($q) use ($request) {
                $q->where('client_id', $request->user()->id)
                  ->orWhere('developer_id', $request->user()->id);
            })
            ->latest()
            ->paginate(15);

        return view('projects.contracts', compact('contracts'));
    }

    // ─── Auth: Contract Detail ───

    public function showContract(Request $request, Project $project)
    {
        $this->middleware('auth');

        $contract = ProjectContract::with(['milestones.escrow', 'project', 'client', 'developer', 'bid'])
            ->where('project_id', $project->id)
            ->firstOrFail();

        // Only parties can view
        if ($contract->client_id !== $request->user()->id && $contract->developer_id !== $request->user()->id) {
            abort(403);
        }

        $isClient = $contract->client_id === $request->user()->id;

        return view('projects.contract-detail', compact('contract', 'isClient'));
    }

    // ─── Auth: My Posted Projects ───

    public function myProjects(Request $request)
    {
        $this->middleware('auth');

        $projects = Project::where('client_id', $request->user()->id)
            ->withCount('bids')
            ->latest()
            ->paginate(15);

        return view('projects.my-projects', compact('projects'));
    }

    // ─── Auth: My Bids ───

    public function myBids(Request $request)
    {
        $this->middleware('auth');

        $bids = ProjectBid::with('project')
            ->where('developer_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return view('projects.my-bids', compact('bids'));
    }

    // ─── Public: Talent Market (developer profiles) ───

    public function talentMarket(Request $request)
    {
        $query = Tenant::where('status', 'active')
            ->whereHas('users', function ($q) {
                $q->where('role', 'developer'); // 只显示有开发者的租户
            })
            ->with(['products' => function ($q) {
                $q->where('status', 'published')->select('tenant_id', 'type')->distinct('type');
            }]);

        // Search
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Filter by product type
        if ($type = $request->input('type')) {
            $query->whereHas('products', function ($q) use ($type) {
                $q->where('type', $type)->where('status', 'published');
            });
        }

        $developers = $query->orderByDesc('developer_level')
            ->orderByDesc('total_sales')
            ->paginate(12)->withQueryString();

        $categories = Project::categories();

        return view('projects.talent', compact('developers', 'categories'));
    }

    // ─── Auth: File Dispute ───

    public function fileDispute(Request $request, ProjectContract $contract)
    {
        $this->middleware('auth');

        $user = $request->user();
        if ($contract->client_id !== $user->id && $contract->developer_id !== $user->id) {
            abort(403);
        }

        // Check no existing open dispute
        $existing = $contract->disputes()->whereNotIn('status', ['closed'])->first();
        if ($existing) {
            return back()->withErrors(['message' => __('dispute.already_exists')]);
        }

        $validated = $request->validate([
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'reason' => 'required|in:' . implode(',', array_keys(ProjectDispute::REASONS)),
            'description' => 'required|string|max:5000',
        ]);

        $dispute = ProjectDispute::create([
            'contract_id' => $contract->id,
            'milestone_id' => $validated['milestone_id'] ?? null,
            'raised_by' => $user->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'status' => 'open',
            'ticket_id' => null, // will be set below
        ]);

        // Create a Ticket for unified admin handling
        $reasonLabel = ProjectDispute::REASONS[$validated['reason']] ?? $validated['reason'];
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => '[Dispute] ' . $contract->project->title . ' — ' . $reasonLabel,
            'message' => $validated['description'],
            'status' => 'open',
            'priority' => 'high',
            'category' => 'dispute',
        ]);
        $dispute->update(['ticket_id' => $ticket->id]);

        // Mark contract as disputed
        $contract->update(['status' => 'disputed']);

        // Notify the other party
        $otherPartyId = $contract->client_id === $user->id ? $contract->developer_id : $contract->client_id;
        $this->notifyUser($otherPartyId, 'project.dispute_filed', [
            'title' => $contract->project->title,
            'reason' => $validated['reason'],
        ]);

        return redirect()->route('projects.disputes.show', $dispute)
            ->with('success', __('dispute.filed_success'));
    }

    // ─── Auth: Show Dispute ───

    public function showDispute(Request $request, ProjectDispute $dispute)
    {
        $this->middleware('auth');

        $contract = $dispute->contract;
        $user = $request->user();
        if ($contract->client_id !== $user->id && $contract->developer_id !== $user->id && !$user->isSuperAdmin()) {
            abort(403);
        }

        $dispute->load(['contract.project', 'contract.milestones', 'raisedByUser', 'resolvedByUser']);
        $reasons = ProjectDispute::reasons();

        return view('projects.dispute-show', compact('dispute', 'reasons'));
    }

    // ─── Auth: My Disputes ───

    public function myDisputes(Request $request)
    {
        $this->middleware('auth');

        $disputes = ProjectDispute::with(['contract.project', 'raisedByUser'])
            ->whereHas('contract', function ($q) use ($request) {
                $q->where('client_id', $request->user()->id)
                  ->orWhere('developer_id', $request->user()->id);
            })
            ->latest()
            ->paginate(15);

        $reasons = ProjectDispute::reasons();
        $statuses = ProjectDispute::statuses();

        return view('projects.disputes', compact('disputes', 'reasons', 'statuses'));
    }

    // ─── Auth: Log Time ───

    public function logTime(Request $request, ProjectContract $contract)
    {
        $this->middleware('auth');

        if ($contract->developer_id !== $request->user()->id) {
            abort(403);
        }

        if (!$contract->isActive()) {
            return back()->withErrors(['message' => __('timelog.contract_not_active')]);
        }

        $validated = $request->validate([
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'description' => 'nullable|string|max:2000',
            'hours' => 'required|numeric|min:0.25|max:24',
            'worked_date' => 'required|date|before_or_equal:today',
        ]);

        $minutes = (int) round($validated['hours'] * 60);
        $hourlyRate = $contract->bid ? ($contract->bid->hourly_rate ?? 0) : 0;
        $amount = round(($validated['hours']) * $hourlyRate, 2);

        ProjectTimeLog::create([
            'contract_id' => $contract->id,
            'milestone_id' => $validated['milestone_id'] ?? null,
            'developer_id' => $request->user()->id,
            'description' => $validated['description'] ?? null,
            'minutes' => $minutes,
            'started_at' => $validated['worked_date'],
            'amount' => $amount,
            'status' => 'pending',
        ]);

        return back()->with('success', __('timelog.logged_success'));
    }

    // ─── Auth: Approve Time Log (Client) ───

    public function approveTimeLog(Request $request, ProjectTimeLog $timeLog)
    {
        $this->middleware('auth');

        $contract = $timeLog->contract;
        if ($contract->client_id !== $request->user()->id) {
            abort(403);
        }

        if (!$timeLog->isPending()) {
            return back()->withErrors(['message' => __('timelog.not_pending')]);
        }

        $timeLog->update(['status' => 'approved']);

        $this->notifyUser($timeLog->developer_id, 'project.timelog_approved', [
            'hours' => $timeLog->hours_formatted,
            'amount' => $timeLog->amount,
        ]);

        return back()->with('success', __('timelog.approved_success'));
    }

    // ─── Auth: Reject Time Log (Client) ───

    public function rejectTimeLog(Request $request, ProjectTimeLog $timeLog)
    {
        $this->middleware('auth');

        $contract = $timeLog->contract;
        if ($contract->client_id !== $request->user()->id) {
            abort(403);
        }

        if (!$timeLog->isPending()) {
            return back()->withErrors(['message' => __('timelog.not_pending')]);
        }

        $timeLog->update(['status' => 'rejected']);

        $this->notifyUser($timeLog->developer_id, 'project.timelog_rejected', [
            'hours' => $timeLog->hours_formatted,
        ]);

        return back()->with('success', __('timelog.rejected_success'));
    }

    // ─── Auth: Time Logs List ───

    public function timeLogs(Request $request, ProjectContract $contract)
    {
        $this->middleware('auth');

        $user = $request->user();
        if ($contract->client_id !== $user->id && $contract->developer_id !== $user->id) {
            abort(403);
        }

        $timeLogs = $contract->timeLogs()
            ->with(['developer', 'milestone'])
            ->latest('started_at')
            ->paginate(20);

        $totalMinutes = $contract->timeLogs()->where('status', '!=', 'rejected')->sum('minutes');
        $totalAmount = $contract->timeLogs()->where('status', '!=', 'rejected')->sum('amount');
        $isDeveloper = $contract->developer_id === $user->id;

        return view('projects.time-logs', compact('contract', 'timeLogs', 'totalMinutes', 'totalAmount', 'isDeveloper'));
    }

    // ─── Auth: My Badges ───

    public function myBadges(Request $request)
    {
        $this->middleware('auth');

        $badges = DeveloperBadge::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $types = DeveloperBadge::TYPES;

        return view('projects.my-badges', compact('badges', 'types'));
    }

    // ─── Auth: My Skills ───

    public function mySkills(Request $request)
    {
        $this->middleware('auth');

        $skills = DeveloperSkill::where('user_id', $request->user()->id)
            ->orderBy('level', 'desc')
            ->get();

        $levels = DeveloperSkill::levelOptions();

        return view('projects.my-skills', compact('skills', 'levels'));
    }

    // ─── Auth: Update Skills ───

    public function updateSkills(Request $request)
    {
        $this->middleware('auth');

        $validated = $request->validate([
            'skills' => 'nullable|array|max:20',
            'skills.*.name' => 'required|string|max:50',
            'skills.*.level' => 'required|integer|min:1|max:5',
        ]);

        $userId = $request->user()->id;

        // Remove all existing and re-create
        DeveloperSkill::where('user_id', $userId)->delete();

        if (!empty($validated['skills'])) {
            foreach ($validated['skills'] as $skillData) {
                DeveloperSkill::create([
                    'user_id' => $userId,
                    'skill' => $skillData['name'],
                    'level' => $skillData['level'],
                    'verified' => false,
                ]);
            }
        }

        return back()->with('success', __('skill.updated_success'));
    }

    // ─── Auth: Project Messages (Private Chat) ───

    public function messages(Request $request, Project $project)
    {
        $this->middleware('auth');

        $user = $request->user();
        // Only client, developer in contract, or admin can view
        $contract = $project->contract;
        if ($project->client_id !== $user->id
            && (!$contract || $contract->developer_id !== $user->id)
            && !$user->isSuperAdmin()) {
            abort(403);
        }

        $messages = ProjectMessage::where('project_id', $project->id)
            ->with('sender')
            ->orderBy('created_at')
            ->paginate(50);

        // Mark unread as read
        ProjectMessage::where('project_id', $project->id)
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $otherParty = null;
        if ($contract) {
            $otherParty = $project->client_id === $user->id ? $contract->developer : $contract->client;
        }

        return view('projects.messages', compact('project', 'messages', 'contract', 'otherParty'));
    }

    public function sendMessage(Request $request, Project $project)
    {
        $this->middleware('auth');

        $user = $request->user();
        $contract = $project->contract;
        if ($project->client_id !== $user->id
            && (!$contract || $contract->developer_id !== $user->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'attachment' => 'nullable|file|max:20480', // 20MB
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('messages/' . $project->id, 'public');
        }

        ProjectMessage::create([
            'project_id' => $project->id,
            'contract_id' => $contract?->id,
            'sender_id' => $user->id,
            'body' => $validated['body'],
            'attachment' => $attachmentPath,
        ]);

        return redirect()->route('projects.messages', $project)
            ->with('success', __('message.sent_success'));
    }

    // ─── AJAX: Poll for new messages ───

    public function messagesPoll(Request $request, Project $project)
    {
        $this->middleware('auth');

        $user = $request->user();
        $contract = $project->contract;
        if ($project->client_id !== $user->id
            && (!$contract || $contract->developer_id !== $user->id)
            && !$user->isSuperAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $afterId = $request->query('after_id', 0);

        $messages = ProjectMessage::where('project_id', $project->id)
            ->where('id', '>', $afterId)
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(function ($msg) use ($user) {
                return [
                    'id' => $msg->id,
                    'body' => $msg->body,
                    'attachment' => $msg->attachment,
                    'sender_name' => $msg->sender->name ?? '-',
                    'is_mine' => $msg->sender_id === $user->id,
                    'time' => $msg->created_at->diffForHumans(),
                ];
            });

        // Mark as read
        if ($messages->isNotEmpty()) {
            ProjectMessage::where('project_id', $project->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json(['messages' => $messages]);
    }

    // ─── Auth: Enterprise Verification ───

    public function showVerification(Request $request)
    {
        $this->middleware('auth');
        return view('projects.verification', ['user' => $request->user()]);
    }

    public function submitVerification(Request $request)
    {
        $this->middleware('auth');

        $validated = $request->validate([
            'account_type' => 'required|in:personal,company',
            'company_name' => 'required_if:account_type,company|nullable|string|max:150',
            'business_license' => 'required_if:account_type,company|nullable|file|mimes:pdf,jpg,png|max:10240',
        ]);

        $user = $request->user();
        $data = [
            'account_type' => $validated['account_type'],
            'company_name' => $validated['company_name'] ?? null,
        ];

        if ($validated['account_type'] === 'company') {
            $data['verification_status'] = 'pending';
            if ($request->hasFile('business_license')) {
                $path = $request->file('business_license')->store('verifications/' . $user->id, 'public');
                $data['business_license'] = $path;
            }
        } else {
            $data['verification_status'] = 'none';
            $data['business_license'] = null;
        }

        $user->update($data);

        return back()->with('success', $validated['account_type'] === 'company'
            ? __('verification.submitted_pending')
            : __('verification.saved_personal'));
    }

    // ─── Phase 3: Credit Score ───

    public function myCreditScore(Request $request, CreditScoreService $scoreService)
    {
        $this->middleware('auth');

        $userId = $request->user()->id;
        $score = DeveloperScore::where('user_id', $userId)->first();

        // Recalculate if not calculated yet or older than 24 hours
        if (!$score || !$score->last_calculated_at || $score->last_calculated_at->diffInHours(now()) > 24) {
            $score = $scoreService->calculate($userId);
        }

        // Get project reviews
        $reviews = ProjectReview::where('reviewee_id', $userId)
            ->with(['reviewer', 'contract.project'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('projects.credit-score', compact('score', 'reviews'));
    }

    // ─── Phase 3: Project Templates ───

    public function templates(Request $request)
    {
        $this->middleware('auth');

        $category = $request->query('category');
        $query = ProjectTemplate::active();

        if ($category) {
            $query->category($category);
        }

        $templates = $query->orderByDesc('use_count')->paginate(12);
        $categories = Project::CATEGORIES;

        return view('projects.templates', compact('templates', 'categories', 'category'));
    }

    public function useTemplate(Request $request, ProjectTemplate $template)
    {
        $this->middleware('auth');

        $template->incrementUseCount();

        // Pre-fill the project creation form with template data
        $prefill = [
            'title' => $template->title,
            'description' => $template->description,
            'category' => $template->category,
            'skills' => $template->skills ?? [],
            'budget_min' => $template->budget_min,
            'budget_max' => $template->budget_max,
            'currency' => $template->currency,
            'duration' => $template->duration,
            'milestones' => $template->milestones ?? [],
        ];

        $categories = Project::categories();
        $templateData = json_encode($prefill);

        return view('projects.create', compact('categories', 'templateData'));
    }

    // ─── Phase 3: Auto-Match Recommendations ───

    public function recommendations(Request $request, ProjectMatchingService $matchingService)
    {
        $this->middleware('auth');

        $userId = $request->user()->id;
        $recommendations = $matchingService->recommend($userId, 10);
        $categories = Project::categories();

        return view('projects.recommendations', compact('recommendations', 'categories'));
    }

    // ─── Phase 3: Project Review ───

    public function reviewContract(Request $request, ProjectContract $contract)
    {
        $this->middleware('auth');

        $user = $request->user();
        // Only client or developer can review
        if ($contract->client_id !== $user->id && $contract->developer_id !== $user->id) {
            abort(403);
        }

        // Contract must be completed
        if (!$contract->isCompleted()) {
            return back()->withErrors(['message' => __('review.contract_not_completed')]);
        }

        // Check if already reviewed by this user
        $existing = ProjectReview::where('contract_id', $contract->id)
            ->where('reviewer_id', $user->id)
            ->first();
        if ($existing) {
            return back()->withErrors(['message' => __('review.already_reviewed')]);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'communication' => 'required|integer|min:1|max:5',
            'quality' => 'required|integer|min:1|max:5',
            'timeliness' => 'required|integer|min:1|max:5',
        ]);

        // Determine reviewee
        $revieweeId = $contract->client_id === $user->id
            ? $contract->developer_id
            : $contract->client_id;

        ProjectReview::create([
            'contract_id' => $contract->id,
            'reviewer_id' => $user->id,
            'reviewee_id' => $revieweeId,
            ...$validated,
        ]);

        // Recalculate reviewee's credit score
        $scoreService = app(CreditScoreService::class);
        $scoreService->calculate($revieweeId);

        return back()->with('success', __('review.submitted_success'));
    }

    // ─── Phase 3: Team Collaboration ───

    public function joinTeam(Request $request, Project $project)
    {
        $this->middleware('auth');

        $user = $request->user();

        // Project must allow teams
        if ($project->max_team_size <= 1) {
            return back()->withErrors(['message' => __('team.not_allowed')]);
        }

        $members = $project->team_members ?? [];

        // Check if already a member
        if (collect($members)->contains('user_id', $user->id)) {
            return back()->withErrors(['message' => __('team.already_member')]);
        }

        // Check team size limit
        if (count($members) >= $project->max_team_size) {
            return back()->withErrors(['message' => __('team.full')]);
        }

        $members[] = [
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now()->toIso8601String(),
        ];

        $project->update(['team_members' => $members]);

        return back()->with('success', __('team.joined_success'));
    }

    public function leaveTeam(Request $request, Project $project)
    {
        $this->middleware('auth');

        $user = $request->user();
        $members = $project->team_members ?? [];
        $members = collect($members)->reject(fn($m) => $m['user_id'] === $user->id)->values()->all();

        $project->update(['team_members' => $members]);

        return back()->with('success', __('team.left_success'));
    }

    // ─── Notification Helper ───

    private function notifyUser(int $userId, string $type, array $data): void
    {
        try {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => "App\\Notifications\\Freelance\\" . class_basename($type),
                'notifiable_type' => User::class,
                'notifiable_id' => $userId,
                'data' => json_encode($data),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Freelance notification failed: " . $e->getMessage());
        }
    }
}
