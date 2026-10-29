<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = null;
    protected static ?string $title = null;
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.settings';

    public static function getNavigationLabel(): string
    {
        return __('系统设置');
    }

    public function getTitle(): string
    {
        return __('系统设置');
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    protected function loadSettings(): array
    {
        return [
            'site_name' => (function() { $v = SiteSetting::get('site_name', config('app.name', 'Platform')); return (__($v) !== $v) ? __($v) : $v; })(),
            'site_logo' => SiteSetting::get('site_logo', ''),
            'site_favicon' => SiteSetting::get('site_favicon', ''),
            'preloader_type' => SiteSetting::get('preloader_type', 'none'),
            'preloader_image' => SiteSetting::get('preloader_image', ''),
            'timezone' => SiteSetting::get('timezone', config('app.timezone', 'Asia/Shanghai')),
                        'default_locale' => SiteSetting::get('default_locale', 'zh_CN'),
            'seo_title' => SiteSetting::get('seo_title', ''),
            'seo_description' => SiteSetting::get('seo_description', ''),
            'seo_keywords' => SiteSetting::get('seo_keywords', ''),
            'footer_copyright' => SiteSetting::get('footer_copyright', ''),
            'contact_email' => SiteSetting::get('contact_email', ''),
            'contact_phone' => SiteSetting::get('contact_phone', ''),
            'contact_address' => SiteSetting::get('contact_address', ''),
            'contact_wechat' => SiteSetting::get('contact_wechat', ''),
            'contact_qq' => SiteSetting::get('contact_qq', ''),
            'contact_telegram' => SiteSetting::get('contact_telegram', ''),
            'contact_whatsapp' => SiteSetting::get('contact_whatsapp', ''),
            'stripe_key' => config('services.stripe.key', ''),
            'stripe_secret' => config('services.stripe.secret', ''),
            'stripe_webhook_secret' => config('services.stripe.webhook_secret', ''),
            'stripe_connect_client_id' => config('services.stripe.connect_client_id', ''),
            'wechat_app_id' => config('services.wechat.app_id', ''),
            'wechat_mch_id' => config('services.wechat.mch_id', ''),
            'wechat_api_key' => config('services.wechat.api_key', ''),
            'wechat_cert_path' => config('services.wechat.cert_path', ''),
            'wechat_notify_url' => env('WECHAT_NOTIFY_URL', ''),
            'wechat_jsapi_auth_url' => env('WECHAT_JSAPI_AUTH_URL', ''),
            'alipay_app_id' => config('services.alipay.app_id', ''),
            'alipay_private_key' => config('services.alipay.private_key', ''),
            'alipay_public_key' => config('services.alipay.alipay_public_key', ''),
            'alipay_gateway' => config('services.alipay.gateway', 'https://openapi.alipay.com/gateway.do'),
            'alipay_sign_type' => env('ALIPAY_SIGN_TYPE', 'RSA2'),
            'alipay_notify_url' => env('ALIPAY_NOTIFY_URL', ''),
            'alipay_return_url' => env('ALIPAY_RETURN_URL', ''),
            'platform_fee_rate' => config('services.platform.fee_rate', 10),
            'payout_min_amount' => config('services.platform.payout_min_amount', 10),
            'payout_delay_days' => config('services.platform.payout_delay_days', 7),
            'affiliate_rate' => SiteSetting::get('affiliate_rate', '10'),
            'affiliate_cookie_days' => SiteSetting::get('affiliate_cookie_days', '30'),
            'affiliate_min_payout' => SiteSetting::get('affiliate_min_payout', '100'),
            'share_enabled' => SiteSetting::get('share_enabled', '1'),
            'share_default_image' => SiteSetting::get('share_default_image', ''),
            'share_default_description' => SiteSetting::get('share_default_description', ''),
            'store_currency' => SiteSetting::get('store_currency', 'CNY'),
            'currency_symbol' => SiteSetting::get('currency_symbol', '¥'),
            'exchange_rate' => SiteSetting::get('exchange_rate', '7.25'),
            'mail_mailer' => env('MAIL_MAILER', 'smtp'),
            'mail_host' => env('MAIL_HOST', ''),
            'mail_port' => env('MAIL_PORT', '587'),
            'mail_username' => env('MAIL_USERNAME', ''),
            'mail_password' => env('MAIL_PASSWORD', ''),
            'mail_encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'mail_from_address' => env('MAIL_FROM_ADDRESS', ''),
            'mail_from_name' => env('MAIL_FROM_NAME', config('app.name', 'Platform')),
            'email_verify_enabled' => SiteSetting::get('email_verify_enabled', '0'),
            'ticket_enabled' => SiteSetting::get('ticket_enabled', '0'),
                        'ticket_auto_reply' => SiteSetting::get('ticket_auto_reply', ''),
                                    'freelance_enabled' => SiteSetting::get('freelance_enabled', '0'),
                                    'freelance_commission_rate' => SiteSetting::get('freelance_commission_rate', '10'),
            // WeChat Open Platform Login
            'reviews_enabled' => SiteSetting::getBoolean('reviews_enabled', config('app.reviews_enabled', true)),
            'wechat_login_enabled' => SiteSetting::get('wechat_login_enabled', '0'),
            'wechat_open_app_id' => SiteSetting::get('wechat_open_app_id', ''),
            'wechat_open_app_secret' => SiteSetting::get('wechat_open_app_secret', ''),
            // SMS settings
            'sms_enabled' => SiteSetting::get('sms_enabled', '0'),
            'sms_provider' => SiteSetting::get('sms_provider', 'aliyun'),
            'sms_aliyun_access_key_id' => env('SMS_ALIYUN_ACCESS_KEY_ID', ''),
            'sms_aliyun_access_key_secret' => env('SMS_ALIYUN_ACCESS_KEY_SECRET', ''),
            'sms_aliyun_sign_name' => env('SMS_ALIYUN_SIGN_NAME', ''),
            'sms_tencent_secret_id' => env('SMS_TENCENT_SECRET_ID', ''),
            'sms_tencent_secret_key' => env('SMS_TENCENT_SECRET_KEY', ''),
            'sms_tencent_sdk_appid' => env('SMS_TENCENT_SDK_APPID', ''),
            'sms_tencent_sign_name' => env('SMS_TENCENT_SIGN_NAME', ''),
            'sms_tpl_verify_code' => SiteSetting::get('sms_tpl_verify_code', ''),
            'sms_tpl_verify_code_enabled' => SiteSetting::get('sms_tpl_verify_code_enabled', '1'),
            'sms_tpl_order_paid' => SiteSetting::get('sms_tpl_order_paid', ''),
            'sms_tpl_order_paid_enabled' => SiteSetting::get('sms_tpl_order_paid_enabled', '1'),
            'sms_tpl_order_shipped' => SiteSetting::get('sms_tpl_order_shipped', ''),
            'sms_tpl_order_shipped_enabled' => SiteSetting::get('sms_tpl_order_shipped_enabled', '1'),
            'sms_tpl_refund_success' => SiteSetting::get('sms_tpl_refund_success', ''),
            'sms_tpl_refund_success_enabled' => SiteSetting::get('sms_tpl_refund_success_enabled', '1'),
            'sms_tpl_refund_rejected' => SiteSetting::get('sms_tpl_refund_rejected', ''),
            'sms_tpl_refund_rejected_enabled' => SiteSetting::get('sms_tpl_refund_rejected_enabled', '1'),
            'sms_tpl_payout_success' => SiteSetting::get('sms_tpl_payout_success', ''),
            'sms_tpl_payout_success_enabled' => SiteSetting::get('sms_tpl_payout_success_enabled', '1'),
            'sms_tpl_payout_failed' => SiteSetting::get('sms_tpl_payout_failed', ''),
            'sms_tpl_payout_failed_enabled' => SiteSetting::get('sms_tpl_payout_failed_enabled', '1'),
            // Cloudflare R2 Storage
            'product_storage_disk' => env('PRODUCT_STORAGE_DISK', 'public'),
            'r2_access_key_id' => env('R2_ACCESS_KEY_ID', ''),
            'r2_secret_access_key' => env('R2_SECRET_ACCESS_KEY', ''),
            'r2_bucket' => env('R2_BUCKET', ''),
            'r2_endpoint' => env('R2_ENDPOINT', ''),
            'r2_custom_domain' => env('R2_CUSTOM_DOMAIN', ''),
            // Alibaba Cloud OSS
            'aliyun_oss_access_key_id' => env('ALIYUN_OSS_ACCESS_KEY_ID', ''),
            'aliyun_oss_access_key_secret' => env('ALIYUN_OSS_ACCESS_KEY_SECRET', ''),
            'aliyun_oss_bucket' => env('ALIYUN_OSS_BUCKET', ''),
            'aliyun_oss_endpoint' => env('ALIYUN_OSS_ENDPOINT', ''),
            'aliyun_oss_custom_domain' => env('ALIYUN_OSS_CUSTOM_DOMAIN', ''),
            // Tencent Cloud COS
            'tencent_cos_secret_id' => env('TENCENT_COS_SECRET_ID', ''),
            'tencent_cos_secret_key' => env('TENCENT_COS_SECRET_KEY', ''),
            'tencent_cos_bucket' => env('TENCENT_COS_BUCKET', ''),
            'tencent_cos_region' => env('TENCENT_COS_REGION', 'ap-guangzhou'),
            'tencent_cos_endpoint' => env('TENCENT_COS_ENDPOINT', ''),
            'tencent_cos_custom_domain' => env('TENCENT_COS_CUSTOM_DOMAIN', ''),
            // Qiniu Cloud
            'qiniu_access_key' => env('QINIU_ACCESS_KEY', ''),
            'qiniu_secret_key' => env('QINIU_SECRET_KEY', ''),
            'qiniu_bucket' => env('QINIU_BUCKET', ''),
            'qiniu_endpoint' => env('QINIU_ENDPOINT', ''),
            'qiniu_custom_domain' => env('QINIU_CUSTOM_DOMAIN', ''),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make(__('基本设置'))
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make(__('站点信息'))
                                    ->description(__('基本站点标识和品牌设置。'))
                                    ->schema([
                                        TextInput::make('site_name')->label(__('站点名称'))->required()->maxLength(100),
                                        FileUpload::make('site_logo')
                                            ->label(__('Logo'))
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/jpeg', 'image/webp'])
                                            ->maxSize(2048)
                                            ->helperText(__('推荐 PNG/SVG 格式，尺寸建议 200x60px，最大 2MB'))
                                            ->deleteUploadedFileUsing(null),
                                        FileUpload::make('site_favicon')
                                            ->label(__('Favicon'))
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/jpeg'])
                                            ->maxSize(512)
                                            ->helperText(__('推荐 PNG/ICO 格式，尺寸建议 32x32px 或 64x64px，最大 512KB'))
                                            ->deleteUploadedFileUsing(null),
                                        Select::make('timezone')->label(__('时区'))
                                            ->options([
                                                'Asia/Shanghai' => 'Asia/Shanghai (CST, UTC+8)',
                                                'Asia/Hong_Kong' => 'Asia/Hong Kong (HKT, UTC+8)',
                                                'Asia/Tokyo' => 'Asia/Tokyo (JST, UTC+9)',
                                                'Asia/Singapore' => 'Asia/Singapore (SGT, UTC+8)',
                                                'America/New_York' => 'America/New York (EST, UTC-5)',
                                                'America/Los_Angeles' => 'America/Los Angeles (PST, UTC-8)',
                                                'Europe/London' => 'Europe/London (GMT, UTC+0)',
                                                'Europe/Berlin' => 'Europe/Berlin (CET, UTC+1)',
                                                'UTC' => 'UTC',
                                            ])->required()
                                            ->helperText(__('用于订单时间戳、报表和后台显示。')),
                                        Select::make('default_locale')->label(__('默认语言'))
                                            ->options([
                                                'zh_CN' => '中文 (Chinese)',
                                                'en' => 'English',
                                            ])->required()
                                            ->helperText(__('前台与后台的默认显示语言。')),
                                    ]),
                                Section::make(__('预加载动画'))
                                    ->description(__('页面加载时显示的全屏动画遮罩，加载完成后自动消失。'))
                                    ->schema([
                                        Select::make('preloader_type')
                                            ->label(__('动画类型'))
                                            ->options([
                                                'none' => __('无'),
                                                'spinner_1' => __('旋转动画 1'),
                                                'spinner_2' => __('旋转动画 2'),
                                                'circle_1' => __('圆形动画 1'),
                                                'circle_2' => __('圆形动画 2'),
                                                'cube_tilt' => __('方块翻转'),
                                                'custom' => __('自定义图像'),
                                            ])
                                            ->default('none')
                                            ->reactive()
                                            ->helperText(__('选择页面加载时显示的动画类型，"无"表示不显示。')),
                                        FileUpload::make('preloader_image')
                                            ->label(__('自定义图像'))
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/gif', 'image/svg+xml', 'image/webp'])
                                            ->maxSize(512)
                                            ->helperText(__('推荐 PNG/GIF/SVG 格式，尺寸建议 100x100px，最大 512KB'))
                                            ->visible(fn ($get) => $get('preloader_type') === 'custom')
                                            ->deleteUploadedFileUsing(null),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('SEO优化'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make(__('搜索引擎优化'))
                                    ->description(__('搜索引擎和社交分享的Meta标签。'))
                                    ->schema([
                                        TextInput::make('seo_title')->label(__('SEO标题'))->maxLength(70)
                                            ->helperText(__('支持翻译键（如 seo.title）或纯文本。')),
                                        Textarea::make('seo_description')->label(__('Meta描述'))->rows(3)->maxLength(160)
                                            ->helperText(__('支持翻译键（如 seo.description）或纯文本。')),
                                        TextInput::make('seo_keywords')->label(__('Meta关键词'))
                                            ->helperText(__('支持翻译键（如 seo.keywords）或逗号分隔文本。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('页脚'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make(__('页脚设置'))
                                    ->description(__('自定义网站页脚内容。'))
                                    ->schema([
                                        TextInput::make('footer_copyright')->label(__('版权文字'))
                                            ->helperText(__('支持：{year}、{site_name}、翻译键（如 footer.copyright）或纯文本。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('联系方式'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make(__('联系信息'))
                                    ->schema([
                                        TextInput::make('contact_email')->label(__('联系邮箱'))
                                            ->helperText(__('例如 support@example.com')),
                                        TextInput::make('contact_phone')->label(__('联系电话'))
                                            ->helperText(__('例如 +86 138-0000-0000')),
                                        TextInput::make('contact_wechat')->label(__('微信号'))
                                            ->helperText(__('微信号，例如 licenseos_official')),
                                        TextInput::make('contact_qq')->label('QQ')
                                            ->helperText(__('QQ号码，例如 123456789')),
                                        TextInput::make('contact_telegram')->label('Telegram')
                                            ->helperText(__('Telegram用户名（不含@），例如 licenseos')),
                                        TextInput::make('contact_whatsapp')->label('WhatsApp')
                                            ->helperText(__('WhatsApp号码（含国际区号），例如 +1 234567890')),
                                        Textarea::make('contact_address')->label(__('联系地址'))->rows(3),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('邮件与功能'))
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make(__('SMTP邮件配置'))
                                    ->description(__('配置发送邮箱，用于密码重置、订单通知等。'))
                                    ->schema([
                                        Select::make('mail_mailer')->label(__('邮件驱动'))
                                            ->options(['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'mailgun' => 'Mailgun', 'ses' => 'Amazon SES', 'log' => __('Log (测试)')])
                                            ->default('smtp'),
                                        TextInput::make('mail_host')->label(__('SMTP主机'))->placeholder('smtp.gmail.com'),
                                        TextInput::make('mail_port')->label(__('SMTP端口'))->numeric()->default(587),
                                        TextInput::make('mail_username')->label(__('SMTP用户名'))->placeholder('noreply@yourdomain.com'),
                                        TextInput::make('mail_password')->label(__('SMTP密码'))->password()->revealable(),
                                        Select::make('mail_encryption')->label(__('加密方式'))
                                            ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => __('无')])->default('tls')
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                match ($state) {
                                                    'ssl' => $set('mail_port', '465'),
                                                    'tls' => $set('mail_port', '587'),
                                                    default => $set('mail_port', '25'),
                                                };
                                            }),
                                        TextInput::make('mail_from_address')->label(__('发件地址'))->placeholder('noreply@yourdomain.com'),
                                        TextInput::make('mail_from_name')->label(__('发件人名称'))->default(config('app.name', 'Platform')),
                                    ]),
                                Section::make(__('邮箱验证'))
                                    ->description(__('启用邮箱验证码，用于用户认证、注册等场景。'))
                                    ->schema([
                                        Toggle::make('email_verify_enabled')->label(__('启用邮箱验证'))
                                            ->helperText(__('启用后，用户可通过邮箱接收6位验证码。需正确配置上方SMTP设置。')),
                                    ]),
                                Section::make(__('发送测试邮件'))
                                    ->description(__('先保存设置，再发送测试邮件验证SMTP配置。'))
                                    ->schema([
                                        TextInput::make('test_email_to')->label(__('发送至'))->placeholder('admin@example.com')->default('admin@example.com'),
                                        Actions::make([
                                            FormAction::make('sendTestEmail')
                                                ->label(__('发送测试邮件'))
                                                ->icon('heroicon-o-paper-airplane')
                                                ->color('info')
                                                ->action('sendTestEmail')
                                                ->requiresConfirmation()
                                                ->modalHeading(__('发送测试邮件'))
                                                ->modalDescription(__('将使用当前SMTP设置向以上地址发送测试邮件。'))
                                                ->modalSubmitActionLabel(__('发送')),
                                        ]),
                                    ]),
                                Section::make(__('工单支持'))
                                    ->description(__('启用或禁用用户工单支持系统。'))
                                    ->schema([
                                        Toggle::make('ticket_enabled')->label(__('启用工单系统'))
                                            ->helperText(__('启用后，登录用户可从账户菜单提交工单。管理员可在工单页面管理和回复。')),
                                        Textarea::make('ticket_auto_reply')->label(__('自动回复模板'))
                                            ->rows(4)
                                            ->placeholder(__('感谢您的工单，我们的团队将在24小时内回复...'))
                                            ->helperText(__('当用户提交新工单时，此消息将作为自动回复发送。留空则禁用。')),
                                    ]),
                                Section::make(__('自由职业市场'))
                                    ->description(__('启用自由职业项目市场，客户发布项目、开发者竞标。'))
                                    ->schema([
                                        Toggle::make('freelance_enabled')->label(__('启用自由职业市场'))
                                            ->helperText(__('启用后，用户可浏览/projects、发布需求，开发者可竞标。')),
                                        TextInput::make('freelance_commission_rate')->label(__('平台佣金比例(%)'))
                                            ->numeric()
                                            ->minValue(0)->maxValue(100)
                                            ->default('10')
                                            ->suffix('%')
                                            ->helperText(__('每笔合同从开发者收入中扣除的百分比。默认：10%。')),
                                    ]),
                                Section::make(__('产品评价'))
                                    ->description(__('产品评价全局总开关，控制所有产品的评价和评分功能。'))
                                    ->schema([
                                        Toggle::make('reviews_enabled')
                                            ->label(__('启用产品评价'))
                                            ->helperText(__('关闭后，所有产品将不显示评价区块，用户也无法提交评价。')),
                                    ]),
                                Section::make(__('微信登录'))
                                    ->description(__('允许用户通过微信开放平台扫码登录(PC)或自动授权(微信内)。'))
                                    ->schema([
                                        Toggle::make('wechat_login_enabled')->label(__('启用微信登录'))
                                            ->helperText(__('启用后，登录/注册页面将显示「微信登录」按钮。')),
                                        TextInput::make('wechat_open_app_id')->label(__('微信开放AppID'))
                                            ->placeholder('wx...')
                                            ->helperText(__('从 open.weixin.qq.com → 应用管理 → 网站应用 获取。')),
                                        TextInput::make('wechat_open_app_secret')->label(__('微信开放AppSecret'))
                                            ->password()->revealable()
                                            ->placeholder('AppSecret')
                                            ->helperText(__('请保密！与AppID同源。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('Stripe支付'))
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Section::make(__('Stripe配置'))
                                    ->description(__('接受全球信用卡/借记卡支付。前往 dashboard.stripe.com 注册。'))
                                    ->schema([
                                        TextInput::make('stripe_key')->label(__('可发布密钥'))->placeholder('pk_test_...')->password()->revealable()
                                            ->helperText(__('从 Stripe Dashboard → Developers → API keys 获取。以 pk_test_ 或 pk_live_ 开头。')),
                                        TextInput::make('stripe_secret')->label(__('密钥'))->placeholder('sk_test_...')->password()->revealable()
                                            ->helperText(__('从 Stripe Dashboard → Developers → API keys 获取。以 sk_test_ 或 sk_live_ 开头。请保密！')),
                                        TextInput::make('stripe_webhook_secret')->label(__('Webhook密钥'))->placeholder('whsec_...')->password()->revealable()
                                            ->helperText(__('在 Stripe Dashboard → Developers → Webhooks 创建。端点: https://yourdomain.com/api/webhook/stripe。复制签名密钥。')),
                                        TextInput::make('stripe_connect_client_id')->label(__('Connect客户端ID'))->placeholder('ca_...')->password()->revealable()
                                            ->helperText(__('可选。Stripe Connect（市场提现）需要。从 Stripe Dashboard → Settings → Connect 获取。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('微信支付'))
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make(__('微信支付配置'))
                                    ->description(__('支持小程序、公众号和扫码支付。前往 pay.weixin.qq.com 获取凭证。'))
                                    ->schema([
                                        TextInput::make('wechat_app_id')->label('AppID')
                                            ->placeholder('wx...')
                                            ->helperText(__('小程序AppID或公众号AppID。从 mp.weixin.qq.com → 设置 → 基本设置 获取。')),
                                        TextInput::make('wechat_mch_id')->label(__('商户号'))
                                            ->placeholder('1234567890')
                                            ->helperText(__('微信支付商户号。从 pay.weixin.qq.com → 账户中心 → 商户信息 获取。')),
                                        TextInput::make('wechat_api_key')->label(__('API v2密钥'))
                                            ->password()->revealable()
                                            ->helperText(__('在 pay.weixin.qq.com → 账户中心 → API安全 → 设置APIv2密钥（32字符）中设置。')),
                                        TextInput::make('wechat_cert_path')->label(__('API证书路径'))
                                            ->placeholder('/path/to/your/project/storage/cert/apiclient_cert.pem')
                                            ->helperText(__('从 pay.weixin.qq.com → 账户中心 → API安全 → 下载证书 下载。上传到服务器后输入完整路径。')),
                                        TextInput::make('wechat_notify_url')->label(__('支付通知地址'))
                                            ->placeholder('https://your-domain.com/api/webhook/wechat')
                                            ->helperText(__('服务器间回调URL。必须是可公开访问的HTTPS地址。')),
                                        TextInput::make('wechat_jsapi_auth_url')->label(__('JSAPI授权目录'))
                                            ->placeholder('https://your-domain.com/')
                                            ->helperText(__('小程序JSAPI支付必需。在 pay.weixin.qq.com → 产品中心 → JSAPI授权目录 设置。必须以斜杠结尾。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('支付宝'))
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Section::make(__('支付宝配置'))
                                    ->description(__('接受支付宝付款。前往 open.alipay.com 创建应用。'))
                                    ->schema([
                                        TextInput::make('alipay_app_id')->label('AppID')->placeholder('2021000000...')
                                            ->helperText(__('从支付宝开放平台 → 我的应用 → 应用详情 获取。通常以2021开头。')),
                                        TextInput::make('alipay_private_key')->label(__('应用私钥'))->password()->revealable()
                                            ->helperText(__('使用支付宝密钥工具生成。粘贴应用私钥（非文件内容）。')),
                                        TextInput::make('alipay_public_key')->label(__('支付宝公钥'))->password()->revealable()
                                            ->helperText(__('在支付宝开放平台上传公钥后，复制平台显示的支付宝公钥（非您自己的公钥）。')),
                                        TextInput::make('alipay_gateway')->label(__('网关地址'))->default('https://openapi.alipay.com/gateway.do')
                                            ->helperText(__('默认: https://openapi.alipay.com/gateway.do（生产环境）。沙箱环境使用 https://openapi.alipaydev.com/gateway.do。')),
                                        Select::make('alipay_sign_type')->label(__('签名方式'))
                                            ->options(['RSA2' => __('RSA2 (推荐)'), 'RSA' => 'RSA'])->default('RSA2')
                                            ->helperText(__('推荐使用RSA2 (SHA256)。必须与支付宝开放平台上的应用设置一致。')),
                                        TextInput::make('alipay_notify_url')->label(__('异步通知地址'))->placeholder('https://yourdomain.com/api/webhook/alipay')
                                            ->helperText(__('服务器间回调。必须可公开访问。在代码中设置，非支付宝平台。')),
                                        TextInput::make('alipay_return_url')->label(__('同步跳转地址'))->placeholder('https://yourdomain.com/checkout/success')
                                            ->helperText(__('支付后用户浏览器跳转URL。通常为支付成功页面。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('平台规则'))
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Section::make(__('佣金与提现规则'))
                                    ->schema([
                                        TextInput::make('platform_fee_rate')->label(__('佣金比例(%)'))->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                                        TextInput::make('payout_min_amount')->label(__('最低提现金额'))->numeric()->minValue(1),
                                        TextInput::make('payout_delay_days')->label(__('提现延迟'))->numeric()->minValue(0)->maxValue(30)->suffix(__('天')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('推广联盟'))
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Section::make(__('推广联盟设置'))
                                    ->description(__('配置推广计划：推广员带来新客户可获得佣金。'))
                                    ->schema([
                                        TextInput::make('affiliate_rate')->label(__('推广佣金比例(%)'))
                                            ->numeric()->minValue(0)->maxValue(50)->suffix('%')->default('10')
                                            ->helperText(__('推广员获得的订单金额百分比。例如10%表示推广员获得每笔推荐订单的10%。')),
                                        TextInput::make('affiliate_cookie_days')->label(__('Cookie有效期(天)'))
                                            ->numeric()->minValue(1)->maxValue(365)->suffix(__('天'))->default('30')
                                            ->helperText(__('推广Cookie的有效期。例如30天表示用户点击推广链接后30天内的购买都归因于该推广员。')),
                                        TextInput::make('affiliate_min_payout')->label(__('推广最低提现'))
                                            ->numeric()->minValue(1)->default('100')
                                            ->helperText(__('推广员可提现的最低余额。')),
                                    ]),
                                Section::make(__('分享设置'))
                                    ->description(__('配置前端社交分享与 SEO 元信息（Open Graph / JSON-LD）。'))
                                    ->schema([
                                        Toggle::make('share_enabled')->label(__('启用社交分享'))
                                            ->default(true)
                                            ->helperText(__('开启后前端展示分享按钮并注入 Open Graph 元信息；关闭后仅保留 meta description 与 JSON-LD。')),
                                        FileUpload::make('share_default_image')
                                            ->label(__('默认分享封面'))
                                            ->directory('settings')
                                            ->visibility('public')
                                            ->image()
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'])
                                            ->maxSize(6144)
                                            ->helperText(__('产品无封面或其他页面分享时使用的默认封面，建议 1200x630px，最大 6MB'))
                                            ->deleteUploadedFileUsing(null),
                                        Textarea::make('share_default_description')->label(__('默认分享描述'))
                                            ->rows(3)
                                            ->helperText(__('产品简介为空或其他页面分享时使用的默认描述。留空则使用 SEO 设置中的站点描述。')),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('货币'))
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Section::make(__('店铺货币'))
                                    ->description(__('设置基础货币和汇率，用于支付转换。'))
                                    ->schema([
                                        Select::make('store_currency')->label(__('基础货币'))
                                            ->options([
                                                'USD' => 'USD - US Dollar', 'CNY' => 'CNY - Chinese Yuan',
                                                'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound',
                                                'JPY' => 'JPY - Japanese Yen', 'HKD' => 'HKD - Hong Kong Dollar',
                                                'SGD' => 'SGD - Singapore Dollar', 'AUD' => 'AUD - Australian Dollar',
                                            ])->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set) {
                                                $map = [
                                                    'USD' => ['$', '1.00'],
                                                    'CNY' => ['¥', '7.25'],
                                                    'EUR' => ['€', '0.92'],
                                                    'GBP' => ['£', '0.79'],
                                                    'JPY' => ['¥', '150.00'],
                                                    'HKD' => ['HK$', '7.80'],
                                                    'SGD' => ['S$', '1.35'],
                                                    'AUD' => ['A$', '1.53'],
                                                ];
                                                if (isset($map[$state])) {
                                                    $set('currency_symbol', $map[$state][0]);
                                                    $set('exchange_rate', $map[$state][1]);
                                                }
                                            }),
                                        TextInput::make('currency_symbol')->label(__('货币符号'))->required()->maxLength(5),
                                        TextInput::make('exchange_rate')->label(__('汇率'))
                                            ->helperText(__('该货币兑1美元的汇率。例如：CNY: 7.25表示1美元=7.25人民币。'))
                                            ->numeric()->minValue(0.001)->step(0.01)->required(),
                                    ]),
                            ]),
                        Tabs\Tab::make(__('对象存储'))
                            ->icon('heroicon-o-cloud')
                            ->schema([
                                Section::make(__('对象存储配置'))
                                    ->description(__('将产品文件存储到云端，而非本地服务器磁盘。'))
                                    ->schema([
                                        Select::make('product_storage_disk')->label(__('存储服务商'))
                                            ->options([
                                                'public' => __('本地(服务器磁盘)'),
                                                'r2' => 'Cloudflare R2',
                                                'aliyun-oss' => __('阿里云OSS'),
                                                'tencent-cos' => __('腾讯云COS'),
                                                'qiniu' => __('七牛云'),
                                            ])->required()
                                            ->reactive()
                                            ->helperText(__('新上传文件将使用选定服务商。其他服务商上的已有文件仍可访问。')),
                                    ]),

                                // --- Cloudflare R2 ---
                                Section::make('Cloudflare R2')
                                    ->description(__('前往 dash.cloudflare.com → R2 → 创建API令牌。'))
                                    ->schema([
                                        TextInput::make('r2_access_key_id')->label('AccessKey ID')->placeholder('a1b2c3d4e5f6...')
                                            ->helperText(__('R2 → 管理R2 API令牌 → 创建API令牌 → 对象读写。')),
                                        TextInput::make('r2_secret_access_key')->label(__('Secret密钥'))->password()->revealable()
                                            ->helperText(__('创建令牌时仅显示一次。请立即保存！')),
                                        TextInput::make('r2_bucket')->label(__('存储桶名称'))->placeholder('licenseos-files'),
                                        TextInput::make('r2_endpoint')->label(__('Endpoint地址'))->placeholder('https://xxxxxxx.r2.cloudflarestorage.com')
                                            ->helperText(__('创建API令牌时显示的S3兼容端点。')),
                                        TextInput::make('r2_custom_domain')->label(__('自定义域名(可选)'))->placeholder('https://cdn.yourdomain.com')
                                            ->helperText(__('如果您为R2存储桶绑定了自定义域名。')),
                                    ])
                                    ->visible(fn (callable $get) => $get('product_storage_disk') === 'r2'),

                                // --- Alibaba Cloud OSS ---
                                Section::make(__('阿里云OSS'))
                                    ->description(__('前往 https://ram.console.aliyun.com → AccessKey 管理。'))
                                    ->schema([
                                        TextInput::make('aliyun_oss_access_key_id')->label('AccessKey ID')->placeholder('LTAI5t...')
                                            ->helperText(__('RAM控制台 → 创建AccessKey。建议使用具有OSS权限的子账号。')),
                                        TextInput::make('aliyun_oss_access_key_secret')->label(__('AccessKey密钥'))->password()->revealable()
                                            ->helperText(__('请保密！')),
                                        TextInput::make('aliyun_oss_bucket')->label(__('存储桶名称'))->placeholder('licenseos-files'),
                                        TextInput::make('aliyun_oss_endpoint')->label('Endpoint')->placeholder('https://oss-cn-hangzhou.aliyuncs.com')
                                            ->helperText(__('格式: https://oss-{region}.aliyuncs.com — 在OSS控制台查看存储桶所在区域。')),
                                        TextInput::make('aliyun_oss_custom_domain')->label(__('自定义域名(可选)'))->placeholder('https://cdn.yourdomain.com')
                                            ->helperText(__('自定义域名必须在OSS控制台进行CNAME绑定。')),
                                    ])
                                    ->visible(fn (callable $get) => $get('product_storage_disk') === 'aliyun-oss'),

                                // --- Tencent Cloud COS ---
                                Section::make(__('腾讯云COS'))
                                    ->description(__('前往 https://console.cloud.tencent.com/cam/capi → SecretId/SecretKey。'))
                                    ->schema([
                                        TextInput::make('tencent_cos_secret_id')->label('SecretId')->placeholder('AKIDxxxxxxxxxx')
                                            ->helperText(__('CAM控制台 → 创建API密钥。建议使用具有COS权限的子账号。')),
                                        TextInput::make('tencent_cos_secret_key')->label('SecretKey')->password()->revealable()
                                            ->helperText(__('请保密！')),
                                        TextInput::make('tencent_cos_bucket')->label(__('存储桶名称'))->placeholder('licenseos-1250000000')
                                            ->helperText(__('格式: {名称}-{APPID}。在COS控制台 → 存储桶列表 查看。')),
                                        Select::make('tencent_cos_region')->label(__('区域'))
                                            ->options([
                                                'ap-guangzhou' => 'Guangzhou (ap-guangzhou)',
                                                'ap-shanghai' => 'Shanghai (ap-shanghai)',
                                                'ap-beijing' => 'Beijing (ap-beijing)',
                                                'ap-chengdu' => 'Chengdu (ap-chengdu)',
                                                'ap-chongqing' => 'Chongqing (ap-chongqing)',
                                                'ap-hongkong' => 'Hong Kong (ap-hongkong)',
                                                'ap-singapore' => 'Singapore (ap-singapore)',
                                                'na-siliconvalley' => 'US West (na-siliconvalley)',
                                            ])->default('ap-guangzhou')
                                            ->helperText(__('必须与COS控制台中的存储桶区域一致。')),
                                        TextInput::make('tencent_cos_endpoint')->label('Endpoint')->placeholder('https://cos.ap-guangzhou.myqcloud.com')
                                            ->helperText(__('格式: https://cos.{region}.myqcloud.com — 留空则根据区域自动填充。')),
                                        TextInput::make('tencent_cos_custom_domain')->label(__('自定义域名(可选)'))->placeholder('https://cdn.yourdomain.com'),
                                    ])
                                    ->visible(fn (callable $get) => $get('product_storage_disk') === 'tencent-cos'),

                                // --- Qiniu Cloud ---
                                Section::make(__('七牛云'))
                                    ->description(__('前往 https://portal.qiniu.com → 个人中心 → 密钥管理。'))
                                    ->schema([
                                        TextInput::make('qiniu_access_key')->label('AccessKey')->placeholder('your-access-key')
                                            ->helperText(__('个人中心 → 密钥管理 → AccessKey。')),
                                        TextInput::make('qiniu_secret_key')->label(__('密钥'))->password()->revealable()
                                            ->helperText(__('请保密！')),
                                        TextInput::make('qiniu_bucket')->label(__('存储桶名称'))->placeholder('licenseos-files'),
                                        TextInput::make('qiniu_endpoint')->label('S3 Endpoint')->placeholder('https://s3-cn-east-1.qiniucs.com')
                                            ->helperText(__('格式: https://s3-{region}.qiniucs.com — 查看七牛云文档获取您所在区域的地址。')),
                                        TextInput::make('qiniu_custom_domain')->label(__('CDN域名'))->placeholder('https://cdn.yourdomain.com')
                                            ->helperText(__('七牛云要求绑定域名才能访问文件。从存储桶设置 → 域名设置 获取。')),
                                    ])
                                    ->visible(fn (callable $get) => $get('product_storage_disk') === 'qiniu'),
                            ]),
                        Tabs\Tab::make(__('短信通知'))
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema([
                                Section::make(__('短信设置'))
                                    ->description(__('配置短信服务，用于验证码、订单通知等。'))
                                    ->schema([
                                        Toggle::make('sms_enabled')->label(__('启用短信'))
                                            ->helperText(__('所有短信功能的总开关。'))
                                            ->reactive(),
                                        Select::make('sms_provider')->label(__('短信服务商'))
                                            ->options([
                                                'aliyun' => __('阿里云'),
                                                'tencent' => __('腾讯云'),
                                            ])
                                            ->reactive()
                                            ->helperText(__('选择短信服务商，仅使用所选服务商的配置。')),
                                    ]),
                                // Alibaba Cloud config
                                Section::make(__('阿里云短信'))
                                    ->description(__('前往 https://ram.console.aliyun.com → AccessKey'))
                                    ->schema([
                                        TextInput::make('sms_aliyun_access_key_id')->label('AccessKey ID')
                                            ->helperText(__('例如 LTAI5t...（阿里云RAM控制台）')),
                                        TextInput::make('sms_aliyun_access_key_secret')->label(__('AccessKey密钥'))
                                            ->password()->revealable()
                                            ->helperText(__('例如 hGx3kP...（请保密，切勿分享）')),
                                        TextInput::make('sms_aliyun_sign_name')->label(__('短信签名'))
                                            ->helperText(__('例如 "您的公司名"（必须与阿里云控制台已审核的签名一致）')),
                                    ])
                                    ->visible(fn (callable $get) => $get('sms_provider') === 'aliyun'),
                                // Tencent Cloud config
                                Section::make(__('腾讯云短信'))
                                    ->description(__('前往 https://console.cloud.tencent.com/cam/capi → SecretId/SecretKey'))
                                    ->schema([
                                        TextInput::make('sms_tencent_secret_id')->label('SecretId')
                                            ->helperText(__('例如 AKIDxxxxxxxxxx（腾讯云CAM控制台）')),
                                        TextInput::make('sms_tencent_secret_key')->label('SecretKey')
                                            ->password()->revealable()
                                            ->helperText(__('例如 xxxxxxxxxx（请保密，切勿分享）')),
                                        TextInput::make('sms_tencent_sdk_appid')->label('SDK AppID')
                                            ->helperText(__('例如 1400000000（腾讯云短信控制台 → 应用管理）')),
                                        TextInput::make('sms_tencent_sign_name')->label(__('短信签名'))
                                            ->helperText(__('例如 "您的公司名"（必须与腾讯云控制台已审核的签名一致）')),
                                    ])
                                    ->visible(fn (callable $get) => $get('sms_provider') === 'tencent'),
                                // Template settings
                                Section::make(__('短信模板'))
                                    ->description(__('从短信服务商控制台获取模板ID，开关控制各通知类型。'))
                                    ->schema([
                                        // 1. Verification Code
                                        TextInput::make('sms_tpl_verify_code')->label(__('验证码'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：您的验证码：${code}，5分钟内有效。请勿泄露。')),
                                        Toggle::make('sms_tpl_verify_code_enabled')->label(__('启用')),
                                        // 2. Order Payment Success
                                        TextInput::make('sms_tpl_order_paid')->label(__('订单支付成功'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：订单 #${ordernum} 支付成功。我们将尽快处理您的订单。')),
                                        Toggle::make('sms_tpl_order_paid_enabled')->label(__('启用')),
                                        // 3. Order Shipment / License Delivery
                                        TextInput::make('sms_tpl_order_shipped')->label(__('订单发货通知'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：您的订单 #${ordernum} 已发货。快递：${express_com}，单号：${express_no}。')),
                                        Toggle::make('sms_tpl_order_shipped_enabled')->label(__('启用')),
                                        // 4. Refund Success
                                        TextInput::make('sms_tpl_refund_success')->label(__('退款成功'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：订单 #${ordernum} 退款成功。金额：${money} 元。请查收。')),
                                        Toggle::make('sms_tpl_refund_success_enabled')->label(__('启用')),
                                        // 5. Refund Rejected
                                        TextInput::make('sms_tpl_refund_rejected')->label(__('退款拒绝'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：抱歉，您的订单 #${ordernum} 退款申请被拒绝。原因：${reason}。')),
                                        Toggle::make('sms_tpl_refund_rejected_enabled')->label(__('启用')),
                                        // 6. Payout Success
                                        TextInput::make('sms_tpl_payout_success')->label(__('提现成功'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：提现成功。金额：${money} 元。请查收。')),
                                        Toggle::make('sms_tpl_payout_success_enabled')->label(__('启用')),
                                        // 7. Payout Failed
                                        TextInput::make('sms_tpl_payout_failed')->label(__('提现失败'))
                                            ->placeholder(__('模板ID'))
                                            ->helperText(__('模板：抱歉，您的提现申请失败。原因：${reason}。')),
                                        Toggle::make('sms_tpl_payout_failed_enabled')->label(__('启用')),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('保存设置'))
                ->action('saveSettings')
                ->color('primary')
                ->size('lg'),
        ];
    }

    public function saveSettings(): void
    {
        try {
            $data = $this->form->getState();

            $siteSettings = [
                'site_name', 'site_logo', 'site_favicon', 'preloader_type', 'preloader_image',
                'seo_title', 'seo_description', 'seo_keywords',
                'footer_copyright',
                'contact_email', 'contact_phone', 'contact_address',
                'contact_wechat', 'contact_qq', 'contact_telegram', 'contact_whatsapp',
                'store_currency', 'currency_symbol', 'exchange_rate',
                'timezone', 'default_locale',
                'email_verify_enabled',
                'ticket_enabled', 'ticket_auto_reply',
                'freelance_enabled', 'freelance_commission_rate',
                'reviews_enabled',
                'wechat_login_enabled', 'wechat_open_app_id', 'wechat_open_app_secret',
                'affiliate_rate', 'affiliate_cookie_days', 'affiliate_min_payout',
                'share_enabled', 'share_default_image', 'share_default_description',
                'sms_enabled', 'sms_provider',
                'sms_tpl_verify_code', 'sms_tpl_verify_code_enabled',
                'sms_tpl_order_paid', 'sms_tpl_order_paid_enabled',
                'sms_tpl_order_shipped', 'sms_tpl_order_shipped_enabled',
                'sms_tpl_refund_success', 'sms_tpl_refund_success_enabled',
                'sms_tpl_refund_rejected', 'sms_tpl_refund_rejected_enabled',
                'sms_tpl_payout_success', 'sms_tpl_payout_success_enabled',
                'sms_tpl_payout_failed', 'sms_tpl_payout_failed_enabled',
            ];

            foreach ($siteSettings as $key) {
                $value = $data[$key] ?? '';
                // Strip outer parentheses from translation key fields
                if (in_array($key, ['seo_title', 'seo_description', 'seo_keywords', 'footer_copyright'])) {
                    $value = trim($value, '()');
                }
                // Convert boolean toggle values to string '1'/'0' for consistent storage
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                SiteSetting::set($key, $value);
            }

            $envPath = base_path('.env');
            $envSaved = false;
            if (file_exists($envPath) && is_writable($envPath)) {
                $envContent = file_get_contents($envPath);
                $envMap = [
                    'stripe_key' => 'STRIPE_KEY', 'stripe_secret' => 'STRIPE_SECRET',
                    'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET', 'stripe_connect_client_id' => 'STRIPE_CONNECT_CLIENT_ID',
                    'wechat_app_id' => 'WECHAT_APP_ID', 'wechat_mch_id' => 'WECHAT_MCH_ID',
                    'wechat_api_key' => 'WECHAT_API_KEY', 'wechat_cert_path' => 'WECHAT_CERT_PATH',
                    'wechat_notify_url' => 'WECHAT_NOTIFY_URL', 'wechat_jsapi_auth_url' => 'WECHAT_JSAPI_AUTH_URL',
                    'alipay_app_id' => 'ALIPAY_APP_ID', 'alipay_private_key' => 'ALIPAY_PRIVATE_KEY',
                    'alipay_public_key' => 'ALIPAY_PUBLIC_KEY', 'alipay_gateway' => 'ALIPAY_GATEWAY',
                    'alipay_sign_type' => 'ALIPAY_SIGN_TYPE', 'alipay_notify_url' => 'ALIPAY_NOTIFY_URL',
                    'alipay_return_url' => 'ALIPAY_RETURN_URL',
                    'mail_mailer' => 'MAIL_MAILER', 'mail_host' => 'MAIL_HOST',
                    'mail_port' => 'MAIL_PORT', 'mail_username' => 'MAIL_USERNAME',
                    'mail_password' => 'MAIL_PASSWORD', 'mail_encryption' => 'MAIL_ENCRYPTION',
                    'mail_from_address' => 'MAIL_FROM_ADDRESS', 'mail_from_name' => 'MAIL_FROM_NAME',
                    'platform_fee_rate' => 'PLATFORM_FEE_RATE', 'payout_min_amount' => 'PAYOUT_MIN_AMOUNT',
                    'payout_delay_days' => 'PAYOUT_DELAY_DAYS',
                    'sms_aliyun_access_key_id' => 'SMS_ALIYUN_ACCESS_KEY_ID',
                    'sms_aliyun_access_key_secret' => 'SMS_ALIYUN_ACCESS_KEY_SECRET',
                    'sms_aliyun_sign_name' => 'SMS_ALIYUN_SIGN_NAME',
                    'sms_tencent_secret_id' => 'SMS_TENCENT_SECRET_ID',
                    'sms_tencent_secret_key' => 'SMS_TENCENT_SECRET_KEY',
                    'sms_tencent_sdk_appid' => 'SMS_TENCENT_SDK_APPID',
                    'sms_tencent_sign_name' => 'SMS_TENCENT_SIGN_NAME',
                    // Cloudflare R2
                    'product_storage_disk' => 'PRODUCT_STORAGE_DISK',
                    'r2_access_key_id' => 'R2_ACCESS_KEY_ID',
                    'r2_secret_access_key' => 'R2_SECRET_ACCESS_KEY',
                    'r2_bucket' => 'R2_BUCKET',
                    'r2_endpoint' => 'R2_ENDPOINT',
                    'r2_custom_domain' => 'R2_CUSTOM_DOMAIN',
                    // Alibaba Cloud OSS
                    'aliyun_oss_access_key_id' => 'ALIYUN_OSS_ACCESS_KEY_ID',
                    'aliyun_oss_access_key_secret' => 'ALIYUN_OSS_ACCESS_KEY_SECRET',
                    'aliyun_oss_bucket' => 'ALIYUN_OSS_BUCKET',
                    'aliyun_oss_endpoint' => 'ALIYUN_OSS_ENDPOINT',
                    'aliyun_oss_custom_domain' => 'ALIYUN_OSS_CUSTOM_DOMAIN',
                    // Tencent Cloud COS
                    'tencent_cos_secret_id' => 'TENCENT_COS_SECRET_ID',
                    'tencent_cos_secret_key' => 'TENCENT_COS_SECRET_KEY',
                    'tencent_cos_bucket' => 'TENCENT_COS_BUCKET',
                    'tencent_cos_region' => 'TENCENT_COS_REGION',
                    'tencent_cos_endpoint' => 'TENCENT_COS_ENDPOINT',
                    'tencent_cos_custom_domain' => 'TENCENT_COS_CUSTOM_DOMAIN',
                    // Qiniu Cloud
                    'qiniu_access_key' => 'QINIU_ACCESS_KEY',
                    'qiniu_secret_key' => 'QINIU_SECRET_KEY',
                    'qiniu_bucket' => 'QINIU_BUCKET',
                    'qiniu_endpoint' => 'QINIU_ENDPOINT',
                    'qiniu_custom_domain' => 'QINIU_CUSTOM_DOMAIN',
                ];

                foreach ($envMap as $formKey => $envKey) {
                    $value = (string) ($data[$formKey] ?? '');
                    $envContent = $this->updateEnvValue($envContent, $envKey, $value);
                }

                file_put_contents($envPath, $envContent);
                $envSaved = true;
            }

            SiteSetting::clearCache();
            try { app('cache')->flush(); } catch (\Throwable $e) {}
            // Clear config cache so .env changes (especially R2 storage) take effect immediately
            @unlink(base_path('bootstrap/cache/config.php'));

            if ($envSaved) {
                Notification::make()->success()->title(__('设置已保存。'))->send();
            } else {
                Notification::make()->warning()
                    ->title(__('数据库设置已保存，但 .env 文件不可写。'))
                    ->body(__('请执行: chmod 664 ') . $envPath)
                    ->duration(8000)->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title(__('保存设置失败'))
                ->body($e->getMessage())
                ->duration(10000)->send();
        }
    }

    public function sendTestEmail(): void
    {
        $data = $this->form->getState();
        $to = trim($data['test_email_to'] ?? '');

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Notification::make()->warning()->title(__('请输入有效的邮箱地址。'))->send();
            return;
        }

        try {
            // Build a custom mailer using the current form values
            $mailer = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $data['mail_host'] ?? 'localhost',
                (int) ($data['mail_port'] ?? 587),
                ($data['mail_encryption'] ?? 'tls') === 'ssl',
            );

            if (!empty($data['mail_encryption']) && $data['mail_encryption'] === 'tls') {
                $mailer->setStartTls(true);
            }

            if (!empty($data['mail_username'])) {
                $mailer->setUsername($data['mail_username']);
            }
            if (!empty($data['mail_password'])) {
                $mailer->setPassword($data['mail_password']);
            }

            $transport = new \Symfony\Component\Mailer\Mailer($mailer);

            $fromAddress = $data['mail_from_address'] ?? config('mail.from.address', 'noreply@example.com');
            $fromName = $data['mail_from_name'] ?? config('mail.from.name', config('app.name', 'Platform'));

            $email = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                ->to($to)
                ->subject("[{$fromName}] " . __('SMTP 测试邮件'))
                ->html('<h2>' . __('SMTP 测试成功') . '</h2><p>' . $fromName . __(' 邮件配置工作正常。') . '</p><p>' . __('时间') . ': ' . now()->toDateTimeString() . '</p>');

            $transport->send($email);

            Notification::make()->success()
                ->title(__('测试邮件发送成功！'))
                ->body(__('测试邮件已发送至 ') . $to . __('，请检查收件箱（含垃圾邮件文件夹）。'))
                ->duration(8000)->send();

        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title(__('发送测试邮件失败'))
                ->body($e->getMessage())
                ->duration(10000)->send();
        }
    }

    protected function updateEnvValue(string $content, string $key, string $value): string
    {
        $needsQuotes = preg_match('/[\s#"\'\\\\]/', $value);
        $envValue = $needsQuotes ? '"' . addcslashes($value, '"\\\\') . '"' : $value;

        $pattern = "/^{$key}=.*/m";
        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, "{$key}={$envValue}", $content);
        }
        return $content . "\n{$key}={$envValue}";
    }
}
