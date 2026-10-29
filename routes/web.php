<?php

use App\Http\Controllers\Auth\CaptchaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\WeChatAuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Developer\AffiliateController;
use App\Http\Controllers\Developer\ApplicationController;
use App\Http\Controllers\Developer\ApiKeyController;
use App\Http\Controllers\Developer\DashboardController;
use App\Http\Controllers\Developer\OrderController;
use App\Http\Controllers\Developer\PayoutController;
use App\Http\Controllers\Developer\ProductController;
use App\Http\Controllers\Developer\ReviewsController;
use App\Http\Controllers\Developer\StatsController;
use App\Http\Controllers\MyLicenseController;
use App\Http\Controllers\MyOrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CmsPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - LicenseOS V3.3
|--------------------------------------------------------------------------
*/

// Homepage / Marketplace
Route::get('/', function () {
    return view('welcome');
})->name('home');

// === Storefront (Public, SEO-friendly) ===
Route::get('/marketplace', [StorefrontController::class, 'index'])->name('marketplace');
Route::get('/marketplace/category/{slug}', [StorefrontController::class, 'category'])->name('marketplace.category');
Route::get('/products/{slug}', [StorefrontController::class, 'show'])->name('product.show');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('sitemap');

// Health check (used by Docker/Nginx/load balancer)
Route::get('/health', function () {
    $checks = [
        'app' => true,
        'database' => false,
        'redis' => false,
    ];

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {}

    try {
        \Illuminate\Support\Facades\Cache::store('redis')->put('health_check', 1, 10);
        $checks['redis'] = true;
    } catch (\Exception $e) {}

    $healthy = $checks['app'] && $checks['database'];

    return response()->json([
        'status' => $healthy ? 'healthy' : 'degraded',
        'checks' => $checks,
        'version' => '3.2',
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
});

// Captcha image (public, no auth required - used by both frontend and admin login)
Route::get('/captcha', [CaptchaController::class, 'generate'])->name('captcha.generate');

// PWA offline fallback
Route::get('/offline', function () { return view('offline'); })->name('offline');

// Redirect /admin/login to unified login
Route::get('/admin/login', function () { return redirect('/login'); });

// GET logout for Filament admin panel (user menu item uses GET links)
Route::get('/admin/logout', [LoginController::class, 'logout'])
    ->middleware('auth')->name('admin.logout');

// === Unified Auth (Guest only) ===
Route::middleware('guest')->group(function () {
    // Login (unified for all roles)
    Route::get('/login', function () { return view('auth.login'); })->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');

    // Register (unified with role selection)
    Route::get('/register', function () { return view('auth.register'); })->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.post');

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])->name('password.update');

    // Developer application page (alternative developer registration via admin review)
    Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
    Route::post('/apply', [ApplicationController::class, 'store'])->name('apply.store');
    Route::get('/apply/submitted', [ApplicationController::class, 'submitted'])->name('apply.submitted');
});

// === WeChat Open Platform Auth ===
Route::get('/auth/wechat', [WeChatAuthController::class, 'redirect'])->name('wechat.redirect');
Route::get('/auth/wechat/callback', [WeChatAuthController::class, 'callback'])->name('wechat.callback');
Route::post('/auth/wechat/bind-login', [WeChatAuthController::class, 'bindLogin'])->name('wechat.bind.login');
Route::post('/auth/wechat/bind-register', [WeChatAuthController::class, 'bindRegister'])->name('wechat.bind.register');
Route::post('/auth/wechat/unbind', [WeChatAuthController::class, 'unbind'])->middleware('auth')->name('wechat.unbind');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')->name('logout');

// === My Licenses & Orders (auth required, any role) ===
Route::middleware(['auth', 'credential.guard'])->group(function () {
    Route::get('/my-licenses', [MyLicenseController::class, 'index'])->name('my-licenses');
    Route::get('/my-orders', [MyOrderController::class, 'index'])->name('my-orders');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');

    Route::post('/reviews/{slug}', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('/reviews/reply/{review}', [ReviewController::class, 'reply'])->name('reviews.reply');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/api/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');

    // Freelance Marketplace (auth)
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/bid', [ProjectController::class, 'bid'])->name('projects.bid');
    Route::post('/projects/bids/{bid}/accept', [ProjectController::class, 'acceptBid'])->name('projects.accept-bid');
    Route::post('/projects/{project}/cancel', [ProjectController::class, 'cancelProject'])->name('projects.cancel');
    Route::post('/projects/milestones/{milestone}/fund', [ProjectController::class, 'fundMilestone'])->name('projects.fund-milestone');
    Route::post('/projects/milestones/{milestone}/submit', [ProjectController::class, 'submitDeliverable'])->name('projects.submit-deliverable');
    Route::post('/projects/milestones/{milestone}/approve', [ProjectController::class, 'approveMilestone'])->name('projects.approve-milestone');
    Route::post('/projects/milestones/{milestone}/revision', [ProjectController::class, 'requestRevision'])->name('projects.request-revision');
    Route::get('/projects/my-projects', [ProjectController::class, 'myProjects'])->name('projects.my-projects');
    Route::get('/projects/my-bids', [ProjectController::class, 'myBids'])->name('projects.my-bids');
    Route::get('/projects/contracts', [ProjectController::class, 'myContracts'])->name('projects.contracts');
    Route::get('/projects/{project}/contract', [ProjectController::class, 'showContract'])->name('projects.contracts.show');

    // Phase 2: Disputes
    Route::get('/projects/disputes', [ProjectController::class, 'myDisputes'])->name('projects.disputes');
    Route::post('/projects/contracts/{contract}/dispute', [ProjectController::class, 'fileDispute'])->name('projects.dispute.file');
    Route::get('/projects/disputes/{dispute}', [ProjectController::class, 'showDispute'])->name('projects.disputes.show');

    // Phase 2: Time Logs
    Route::get('/projects/contracts/{contract}/time-logs', [ProjectController::class, 'timeLogs'])->name('projects.time-logs');
    Route::post('/projects/contracts/{contract}/time-logs', [ProjectController::class, 'logTime'])->name('projects.time-logs.store');
    Route::post('/projects/time-logs/{timeLog}/approve', [ProjectController::class, 'approveTimeLog'])->name('projects.time-logs.approve');
    Route::post('/projects/time-logs/{timeLog}/reject', [ProjectController::class, 'rejectTimeLog'])->name('projects.time-logs.reject');

    // Phase 2: Badges & Skills
    Route::get('/projects/my-badges', [ProjectController::class, 'myBadges'])->name('projects.badges');
    Route::get('/projects/my-skills', [ProjectController::class, 'mySkills'])->name('projects.skills');
    Route::post('/projects/skills', [ProjectController::class, 'updateSkills'])->name('projects.skills.update');

    // Phase 2.5: Messages (private chat)
    Route::get('/projects/{project}/messages', [ProjectController::class, 'messages'])->name('projects.messages');
    Route::post('/projects/{project}/messages', [ProjectController::class, 'sendMessage'])->name('projects.messages.send');

    // Phase 3: Credit Score
    Route::get('/projects/my-score', [ProjectController::class, 'myCreditScore'])->name('projects.score');

    // Phase 3: Project Templates
    Route::get('/projects/templates/browse', [ProjectController::class, 'templates'])->name('projects.templates');
    Route::get('/projects/templates/{template}', [ProjectController::class, 'useTemplate'])->name('projects.templates.use');

    // Phase 3: Auto-Match Recommendations
    Route::get('/projects/recommendations', [ProjectController::class, 'recommendations'])->name('projects.recommendations');

    // Phase 3: Project Review
    Route::post('/projects/contracts/{contract}/review', [ProjectController::class, 'reviewContract'])->name('projects.review');

    // Phase 3: Team Collaboration
    Route::post('/projects/{project}/team/join', [ProjectController::class, 'joinTeam'])->name('projects.team.join');
    Route::post('/projects/{project}/team/leave', [ProjectController::class, 'leaveTeam'])->name('projects.team.leave');

    // AJAX: Messages polling
    Route::get('/projects/{project}/messages/poll', [ProjectController::class, 'messagesPoll'])->name('projects.messages.poll');

    // Enterprise Verification
    Route::get('/projects/verification', [ProjectController::class, 'showVerification'])->name('projects.verification');
    Route::post('/projects/verification', [ProjectController::class, 'submitVerification'])->name('projects.verification.submit');
});

// Freelance Marketplace (public browse)
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/talent', [ProjectController::class, 'talentMarket'])->name('projects.talent');
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

// === Checkout (auth required, rate limited) ===
Route::middleware(['auth', 'credential.guard', 'throttle:5,10'])->group(function () {
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::post('/checkout/direct', [CheckoutController::class, 'create'])->name('checkout.direct');
    Route::get('/checkout/status/{orderId}', [CheckoutController::class, 'status'])->name('checkout.status');
});

// === Payment Webhooks (no auth, called by payment providers) ===
Route::post('/api/webhook/wechat', [WebhookController::class, 'wechat']);
Route::post('/api/webhook/alipay', [WebhookController::class, 'alipay']);

// === SMS API (no auth, rate-limited) ===
Route::post('/api/sms/send-code', [SmsController::class, 'sendCode']);
Route::post('/api/sms/verify', [SmsController::class, 'verify']);

// === Email Verification API (no auth, rate-limited) ===
Route::post('/api/email/send-code', [EmailVerificationController::class, 'sendCode']);
Route::post('/api/email/verify', [EmailVerificationController::class, 'verify']);

/*
|--------------------------------------------------------------------------
| Developer Dashboard (auth + role: developer/super_admin)
|--------------------------------------------------------------------------
*/
Route::prefix('developer')->middleware(['auth', 'credential.guard', 'role:developer,super_admin'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('developer.dashboard');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('developer.orders.index');

    // Product management
    Route::get('/products', [ProductController::class, 'index'])->name('developer.products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('developer.products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('developer.products.store');
    Route::post('/products/scan-zip', [ProductController::class, 'scanZip'])->name('developer.products.scan-zip');
    Route::post('/products/parse-zip', [ProductController::class, 'parseZip'])->name('developer.products.parse-zip');
    Route::post('/products/{product}/parse-readme', [ProductController::class, 'parseReadme'])->name('developer.products.parse-readme');
    Route::post('/products/{product}/upload-screenshot', [ProductController::class, 'uploadScreenshot'])->name('developer.products.upload-screenshot');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('developer.products.show');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('developer.products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('developer.products.destroy');
    Route::post('/products/{product}/upload', [ProductController::class, 'uploadVersion'])->name('developer.products.upload-version');
    // One-click inject/uninject handled in the same product detail page
    Route::post('/products/{product}', [ProductController::class, 'handleAction'])->name('developer.products.action');
    Route::post('/products/{product}/pricing', [ProductController::class, 'updatePricing'])->name('developer.products.update-pricing');

    // Slug uniqueness check (AJAX)
    Route::post('/check-slug', [ProductController::class, 'checkSlug'])->name('developer.check-slug');

    // API Key management
    Route::get('/api-keys', [ApiKeyController::class, 'index'])->name('developer.api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])->name('developer.api-keys.store');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('developer.api-keys.destroy');

    // Payout management
    Route::get('/payouts', [PayoutController::class, 'index'])->name('developer.payouts.index');
    Route::post('/payouts', [PayoutController::class, 'store'])->name('developer.payouts.store');
    Route::put('/payouts/accounts', [PayoutController::class, 'updateAccounts'])->name('developer.payouts.accounts');

    // V3: Sales Analytics
    Route::get('/stats', [StatsController::class, 'index'])->name('developer.stats.index');
    Route::get('/stats/product/{product}', [StatsController::class, 'product'])->name('developer.stats.product');

    // V3: Reviews Management
    Route::get('/reviews', [ReviewsController::class, 'index'])->name('developer.reviews.index');
    Route::post('/reviews/{review}/approve', [ReviewsController::class, 'approve'])->name('developer.reviews.approve');
    Route::post('/reviews/{review}/reject', [ReviewsController::class, 'reject'])->name('developer.reviews.reject');
});

// === Affiliate (all authenticated users, not just developers) ===
Route::prefix('affiliate')->middleware(['auth', 'credential.guard'])->group(function () {
    Route::get('/', [AffiliateController::class, 'index'])->name('affiliate.index');
    Route::post('/regenerate', [AffiliateController::class, 'regenerateCode'])->name('affiliate.regenerate');
    Route::post('/withdraw', [AffiliateController::class, 'withdraw'])->name('affiliate.withdraw');
});

// Public info pages
Route::get('/faq', function () {
    return view('faq');
})->name('faq');

// === Contact ===
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
Route::redirect('/message', '/contact');

Route::get('/docs', function () {
    return view('docs');
})->name('docs');

// === CMS Pages (dynamic, from admin) ===
Route::get('/page/{slug}', [CmsPageController::class, 'show'])->name('cms.page');
