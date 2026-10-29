<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Tenant;
use App\Models\Category;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\ProductStorageService;
use App\Services\WpPluginParserService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('admin.product.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.product.label');
    }

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.products');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Product')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    // ── Tab: Basic Info ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.basic'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Select::make('tenant_id')
                                ->label(__('所属开发者'))
                                ->relationship('tenant')
                                ->getOptionLabelFromRecordUsing(fn (Tenant $record) => trim(($record->name ?? '') . ' - ' . ($record->email ?? '')))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Forms\Components\TextInput::make('name')
                                ->label(__('admin.common.name'))
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Forms\Set $set, ?string $state) =>
                                    $set('slug', Str::slug($state))
                                ),
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->unique(ignoreRecord: true),
                            Forms\Components\Select::make('type')
                                ->label(__('admin.common.type'))
                                ->options(Product::TYPES)
                                ->default('wp_plugin')
                                ->searchable()
                                ->live(),
                            Forms\Components\Select::make('category_id')
                                ->label(__('admin.product.category'))
                                ->options(Category::pluck('name', 'id'))
                                ->searchable(),
                            Forms\Components\TextInput::make('version')
                                ->label(__('admin.common.version'))
                                ->default('1.0.0'),
                            Forms\Components\Select::make('status')
                                ->label(__('admin.common.status'))
                                ->options([
                                    'draft' => __('admin.product.opt.draft'),
                                    'published' => __('admin.product.opt.published'),
                                    'archived' => __('admin.product.opt.archived'),
                                ])
                                ->default('draft'),
                            Forms\Components\TextInput::make('price')->numeric()
                                ->label(__('admin.product.price'))
                                ->prefix(SiteSetting::get('currency_symbol', '¥'))->default(0)
                                ->helperText(__('admin.product.helper.price')),
                            Forms\Components\Toggle::make('featured')
                                ->label(__('admin.product.featured'))
                                ->default(false),
                            Forms\Components\Toggle::make('reviews_enabled')
                                ->label(__('启用评价'))
                                ->default(true)
                                ->helperText(__('允许用户对该产品提交评价和评分。')),
                        ])->columns(2),

                    // ── Tab: Readme Sections (plain text, mirrors developer frontend) ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.readme'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Forms\Components\TextInput::make('short_description')
                                ->label(__('admin.product.short_description'))
                                ->maxLength(255),
                            Forms\Components\Textarea::make('description')
                                ->label(__('admin.common.description'))
                                ->rows(10)
                                ->helperText(__('admin.product.helper.description_plain'))
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('installation')
                                ->label(__('dev_products.installation_instructions'))
                                ->rows(8)
                                ->hint(__('admin.product.helper.readme_text'))
                                ->columnSpanFull(),
                            Forms\Components\Textarea::make('faq')
                                ->label(__('dev_products.faq_title'))
                                ->rows(8)
                                ->hint(__('admin.product.helper.readme_text'))
                                ->columnSpanFull(),
                        ]),

                    // ── Tab: Images ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.images'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\FileUpload::make('cover_image')
                                ->label(__('封面图'))
                                ->image()
                                ->directory('covers')
                                ->helperText(__('产品封面图，推荐正方形或 16:9 比例')),
                            Forms\Components\Repeater::make('screenshots')
                                ->label(__('admin.product.screenshots'))
                                ->schema([
                                    Forms\Components\FileUpload::make('url')
                                        ->label(__('admin.product.screenshot_image'))
                                        ->image()
                                        ->disk('public')
                                        ->directory('screenshots'),
                                    Forms\Components\TextInput::make('caption')
                                        ->label(__('admin.product.caption')),
                                ])
                                ->addActionLabel(__('admin.product.add_screenshot'))
                                ->maxItems(8)
                                ->reorderable()
                                ->collapsible()
                                ->columns(2),
                        ]),

                    // ── Tab: Pricing ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.pricing'))
                        ->icon('heroicon-o-currency-dollar')
                        ->schema([
                            Forms\Components\Toggle::make('is_free_product')
                                ->label(__('admin.product.is_free_product'))
                                ->default(false)
                                ->live()
                                ->helperText(__('admin.product.helper.is_free')),
                            Forms\Components\Repeater::make('pricing_tiers')
                                ->label(__('admin.product.pricing_tiers'))
                                ->hidden(fn (Forms\Get $get) => (bool) $get('is_free_product'))
                                ->schema([
                                    Forms\Components\Toggle::make('enabled')
                                        ->label(__('admin.product.enable'))
                                        ->default(true),
                                    Forms\Components\Select::make('type')
                                        ->label(__('admin.product.duration_type'))
                                        ->options(Product::TIER_TYPES)
                                        ->required(),
                                    Forms\Components\TextInput::make('days')
                                        ->label(__('admin.product.days_trial'))
                                        ->numeric()
                                        ->helperText(__('admin.product.helper.days')),
                                    Forms\Components\TextInput::make('months')
                                        ->label(__('admin.product.months'))
                                        ->numeric()
                                        ->default(0)
                                        ->helperText(__('admin.product.helper.months')),
                                    Forms\Components\TextInput::make('price')
                                        ->label(__('admin.product.price'))
                                        ->numeric()
                                        ->prefix(SiteSetting::get('currency_symbol', '¥'))
                                        ->default(0),
                                    Forms\Components\TextInput::make('label')
                                        ->label(__('admin.product.display_label')),
                                    Forms\Components\Textarea::make('description')
                                        ->label(__('admin.product.description'))
                                        ->rows(2),
                                    Forms\Components\Toggle::make('auto_renew')
                                        ->label(__('admin.product.auto_renew'))
                                        ->helperText(__('admin.product.helper.auto_renew')),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel(__('admin.product.add_tier'))
                                ->columns(4),
                        ]),

                    // ── Tab: SEO ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.seo'))
                        ->icon('heroicon-o-magnifying-glass')
                        ->schema([
                            Forms\Components\TextInput::make('meta_title')
                                ->label(__('admin.product.meta_title'))
                                ->maxLength(255),
                            Forms\Components\Textarea::make('meta_description')
                                ->label(__('admin.product.meta_description'))
                                ->rows(3)
                                ->maxLength(500),
                            Forms\Components\TagsInput::make('keywords')
                                ->label(__('admin.product.keywords'))
                                ->placeholder('keyword1, keyword2...'),
                        ]),

                    // ── Tab: WP Metadata (visible fields for wp_plugin / wp_theme) ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.wp_meta'))
                        ->icon('heroicon-o-puzzle-piece')
                        ->schema([
                            Forms\Components\FileUpload::make('plugin_zip')
                                ->label(__('插件/主题 ZIP 文件'))
                                ->disk('public')
                                ->directory('temp-uploads')
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme']))
                                ->live() // 关键：启用实时状态绑定
                                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (!$state) return;

                                try {
                                    // Step 1: Call scan-zip API to check security
                                    $scanUrl = route('developer.products.scan-zip');

                                    $httpClient = new \GuzzleHttp\Client();
                                    $fileDiskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($state);

                                    $response = $httpClient->post($scanUrl, [
                                        'headers' => [
                                            'Accept' => 'application/json',
                                            'X-CSRF-TOKEN' => csrf_token(),
                                        ],
                                        'multipart' => [[
                                            'name' => 'file',
                                            'contents' => fopen($fileDiskPath, 'r'),
                                            'filename' => basename($fileDiskPath),
                                        ]],
                                    ]);

                                    $result = json_decode((string)$response->getBody(), true);

                                    if (!isset($result['safe']) || !$result['safe']) {
                                        // Security check failed
                                        $issues = $result['issues'] ?? [];
                                        $warningMessage = isset($result['message']) ? $result['message'] :
                                                         (count($issues) > 0 ? implode(', ', array_slice($issues, 0, 3)) : '安全检测未通过');

                                        \Filament\Notifications\Notification::make()
                                            ->title(__('安全扫描失败'))
                                            ->body($warningMessage)
                                            ->danger()
                                            ->send();

                                        // Clear the file upload field
                                        $set('plugin_zip', null);
                                        return;
                                    }

                                    // Step 2: Parse metadata (scan passed)
                                    $disk = \Illuminate\Support\Facades\Storage::disk('public');
                                    $filePath = $disk->path($state);

                                    $parser = app(\App\Services\WpPluginParserService::class);
                                    $metadata = $parser->parseFromPath($filePath);

                                    foreach ([
                                        'name' => __('产品名称'),
                                        'version' => __('版本号'),
                                        'author' => __('作者'),
                                        'author_profile' => __('作者主页'),
                                        'plugin_homepage' => __('插件主页'),
                                        'short_description' => __('简短描述'),
                                        'requires_wp' => __('WordPress 最低版本'),
                                        'tested_up_to' => __('测试版本'),
                                        'requires_php' => __('PHP 最低版本'),
                                        'license' => __('授权协议'),
                                        'license_uri' => __('授权链接'),
                                        'text_domain' => __('文本域'),
                                        'wp_tags' => __('WordPress 标签'),
                                    ] as $field => $label) {
                                        if (!empty($metadata[$field])) {
                                            $set($field, $metadata[$field]);
                                        }
                                    }

                                    // Write back readme_sections / readme_raw + description/installation/faq
                                    // (mirrors Developer\ProductController::parseReadme / store)
                                    $sections = $metadata['readme_sections'] ?? [];
                                    if (!empty($sections)) {
                                        $set('readme_sections', json_encode($sections, JSON_UNESCAPED_UNICODE));

                                        // description always follows the readme Description section
                                        if (!empty($sections['description'])) {
                                            $set('description', $sections['description']);
                                        }
                                        // installation / faq only when the form field is empty
                                        if (!empty($sections['installation']) && empty($get('installation'))) {
                                            $set('installation', $sections['installation']);
                                        }
                                        if (!empty($sections['faq']) && empty($get('faq'))) {
                                            $set('faq', $sections['faq']);
                                        }
                                    }
                                    if (!empty($metadata['readme_raw'])) {
                                        $set('readme_raw', $metadata['readme_raw']);
                                    }

                                    // Sync tags parsed from readme (wp_tags comma string -> tags array)
                                    if (!empty($metadata['tags']) && is_array($metadata['tags'])) {
                                        $set('tags', array_values($metadata['tags']));
                                    } elseif (!empty($metadata['wp_tags'])) {
                                        $set('tags', array_values(array_filter(array_map('trim', explode(',', $metadata['wp_tags'])))));
                                    }

                                    \Filament\Notifications\Notification::make()
                                        ->title(__('✅ 安全扫描通过 & 插件信息已自动提取'))
                                        ->body(__('已自动填充名称、版本、作者等字段，请检查并按需修改'))
                                        ->success()
                                        ->send();
                                } catch (\Throwable $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title(__('ZIP 处理失败'))
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                    // Clear the file upload field on error
                                    $set('plugin_zip', null);
                                }
                            }),
                            Forms\Components\TextInput::make('author')
                                ->label(__('作者'))
                                ->helperText(__('从 ZIP 自动提取，可手动修改'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('author_profile')
                                ->label(__('作者主页'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('plugin_homepage')
                                ->label(__('插件主页'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('requires_wp')
                                ->label(__('最低 WordPress 版本'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('tested_up_to')
                                ->label(__('测试到的最高 WordPress 版本'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('requires_php')
                                ->label(__('最低 PHP 版本'))
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('license')
                                ->label(__('dev_products.license'))
                                ->maxLength(100)
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('license_uri')
                                ->label(__('dev_products.license_uri'))
                                ->maxLength(255)
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('text_domain')
                                ->label(__('dev_products.text_domain'))
                                ->maxLength(100)
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                            Forms\Components\TextInput::make('wp_tags')
                                ->label(__('dev_products.wp_tags'))
                                ->maxLength(500)
                                ->visible(fn (Forms\Get $get) => in_array($get('type'), ['wp_plugin', 'wp_theme'])),
                        ])
                        ->columns(2),

                    // ── Tab: Misc ──
                    Forms\Components\Tabs\Tab::make(__('admin.product.tab.misc'))
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->schema([
                            Forms\Components\Toggle::make('license_protection_enabled')
                                ->label(__('启用授权保护'))
                                ->helperText(__('启用后，上传的插件 ZIP 将自动注入授权检查代码。用户必须在 WordPress 后台激活许可证后才能使用插件设置页面。'))
                                ->default(false),
                            Forms\Components\TagsInput::make('tags')
                                ->label(__('admin.product.tags'))
                                ->placeholder(__('admin.product.add_tags')),
                            Forms\Components\Select::make('supported_locales')
                                ->label(__('admin.product.supported_locales'))
                                ->options([
                                    'zh_CN' => '中文',
                                    'en' => 'English',
                                ])
                                ->default(['zh_CN', 'en']),
                            Forms\Components\TextInput::make('downloads')
                                ->label(__('admin.product.downloads'))
                                ->numeric()
                                ->default(0)
                                ->disabled(),
                            Forms\Components\TextInput::make('rating')
                                ->label(__('admin.product.rating'))
                                ->numeric()
                                ->default(0)
                                ->disabled(),
                        ])->columns(2),
                ]),

            // Hidden carriers for readme data parsed from the ZIP (never edited directly)
            Forms\Components\Hidden::make('readme_sections')
                ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE) : (string) ($state ?? ''))
                ->dehydrateStateUsing(fn ($state) => is_array($state) ? $state : (json_decode((string) $state, true) ?: [])),
            Forms\Components\Hidden::make('readme_raw'),
        ]);
    }

    /**
     * Shared save-time business rules for Create/Edit pages.
     * Mirrors Developer\ProductController::buildPricingTiers() and readme sync behavior.
     */
    public static function applyProductSaveRules(array $data): array
    {
        $isFree = !empty($data['is_free_product']);
        unset($data['is_free_product']); // form-only field, never persisted

        // Normalize pricing tiers (mirror frontend buildPricingTiers)
        $tiers = [];
        foreach ((array) ($data['pricing_tiers'] ?? []) as $row) {
            if (!is_array($row)) continue; // defensive: corrupt/legacy rows
            $type = $row['type'] ?? '';
            if (!$type) continue;
            $tiers[] = [
                'type'        => $type,
                'enabled'     => !empty($row['enabled']),
                'days'        => (int) ($row['days'] ?? 0),
                'months'      => (int) ($row['months'] ?? 0),
                'price'       => $type === 'trial' ? 0.00 : (float) ($row['price'] ?? 0), // trial 强制免费
                'label'       => $row['label'] ?? '',
                'description' => $row['description'] ?? '',
                'auto_renew'  => !empty($row['auto_renew']),
            ];
        }

        // No tiers left → default to an enabled 7-day trial (product becomes free)
        if (empty($tiers)) {
            $tiers[] = [
                'type'        => 'trial',
                'enabled'     => true,
                'days'        => 7,
                'months'      => 0,
                'price'       => 0.00,
                'label'       => 'Free Trial',
                'description' => '',
                'auto_renew'  => false,
            ];
        }

        // Free product toggle wins: single free tier
        if ($isFree) {
            $tiers = [['type' => 'free', 'enabled' => true, 'price' => 0, 'label' => 'Free']];
        }

        $data['pricing_tiers'] = $tiers;

        // Trial-only / free product → force price to 0 (mirror frontend hasOnlyTrialTier)
        $onlyTrial = !empty($tiers) && empty(array_filter($tiers, fn ($t) => ($t['type'] ?? '') !== 'trial'));
        if ($isFree || $onlyTrial) {
            $data['price'] = 0;
        }

        // Sync readme_sections with the description/installation/faq form fields.
        // changelog / screenshots keep their original values; null sections initialized as array.
        $sections = $data['readme_sections'] ?? null;
        if (is_string($sections)) {
            $sections = json_decode($sections, true);
        }
        if (!is_array($sections)) {
            $sections = [];
        }
        $sections['description']  = (string) ($data['description'] ?? '');
        $sections['installation'] = (string) ($data['installation'] ?? '');
        $sections['faq']          = (string) ($data['faq'] ?? '');
        $data['readme_sections']  = $sections;

        // Normalize screenshots: relative storage path -> public URL (mirror frontend uploadScreenshot)
        $screenshots = [];
        foreach ((array) ($data['screenshots'] ?? []) as $shot) {
            if (is_string($shot)) {
                // Legacy format: plain URL string per entry
                $shot = ['url' => $shot, 'caption' => ''];
            }
            if (!is_array($shot)) {
                continue;
            }
            $url = trim((string) ($shot['url'] ?? ''));
            if ($url === '') continue;
            $shot['url'] = static::screenshotPathToUrl($url);
            $screenshots[] = $shot;
        }
        $data['screenshots'] = array_slice(array_values($screenshots), 0, 8);

        // keywords must be an array (model casts it as array)
        if (isset($data['keywords']) && is_string($data['keywords'])) {
            $data['keywords'] = array_values(array_filter(array_map('trim', explode(',', $data['keywords']))));
        }

        return $data;
    }

    /**
     * Convert a relative public-disk path to a public URL (keeps absolute URLs untouched).
     */
    public static function screenshotPathToUrl(?string $value): ?string
    {
        if (!$value) {
            return $value;
        }
        if (preg_match('#^https?://#', $value)) {
            return $value;
        }
        if (Storage::disk('public')->exists($value)) {
            return Storage::url($value);
        }
        return $value;
    }

    /**
     * Convert a stored public URL back to a relative public-disk path (for FileUpload preview).
     */
    public static function screenshotUrlToPath(?string $value): ?string
    {
        if (!$value) {
            return $value;
        }
        $path = $value;
        if (preg_match('#^https?://#', $path)) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?? '');
        }
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        return $path;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id')),
                Tables\Columns\TextColumn::make('name')->searchable()->label(__('admin.common.name'))->limit(30),
                Tables\Columns\TextColumn::make('type')->label(__('admin.common.type'))
                    ->formatStateUsing(fn (?string $state): string => Product::TYPES[$state ?? ''] ?? ($state ?? ''))
                    ->badge(),
                Tables\Columns\TextColumn::make('category.name')->label(__('admin.product.category')),
                Tables\Columns\TextColumn::make('price')
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')->label(__('admin.common.version')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('featured')
                    ->label(__('admin.product.featured'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('downloads')->label(__('admin.product.downloads'))->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(Product::TYPES)
                    ->label(__('admin.common.type')),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => __('admin.product.opt.draft'),
                        'published' => __('admin.product.opt.published'),
                        'archived' => __('admin.product.opt.archived'),
                    ])
                    ->label(__('admin.common.status')),
                Tables\Filters\SelectFilter::make('category_id')
                    ->options(Category::pluck('name', 'id'))
                    ->label(__('admin.product.category')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('delete')
                    ->label(__('admin.common.delete'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.product.confirm_delete'))
                    ->modalDescription(__('admin.product.delete_warning'))
                    ->action(function (Product $record) {
                        try {
                            $record->forceDelete();
                            \Filament\Notifications\Notification::make()
                                ->title(__('admin.product.deleted'))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title(__('admin.product.delete_failed'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->label(__('admin.common.delete_selected'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('admin.product.confirm_delete_selected'))
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            try {
                                foreach ($records as $record) {
                                    $record->forceDelete();
                                }
                                \Filament\Notifications\Notification::make()
                                    ->title(__('admin.product.deleted_count', ['count' => $records->count()]))
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('admin.product.delete_failed'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
