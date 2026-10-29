<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendNotification extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $title = null;
    protected static ?int $navigationSort = 52;

    protected static string $view = 'filament.pages.send-notification';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.announce.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.announce.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.announce.title');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('audience')
                    ->label(__('admin.announce.audience'))
                    ->options([
                        'all'        => __('admin.announce.opt.all'),
                        'customers'  => __('admin.announce.opt.customers'),
                        'developers' => __('admin.announce.opt.developers'),
                        'specific'   => __('admin.announce.opt.specific'),
                    ])
                    ->default('all')
                    ->required()
                    ->live(),

                Forms\Components\Select::make('user_ids')
                    ->label(__('admin.announce.select_users'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => User::whereNotNull('email_verified_at')
                        ->orWhere('role', '!=', null)
                        ->pluck('name', 'id')
                        ->mapWithKeys(fn ($name, $id) => [$id => $name . ' (ID:' . $id . ')'])
                    )
                    ->visible(fn (Forms\Get $get) => $get('audience') === 'specific')
                    ->required(fn (Forms\Get $get) => $get('audience') === 'specific')
                    ->helperText(__('admin.announce.select_users_help')),

                Forms\Components\TextInput::make('title')
                    ->label(__('admin.announce.field_title'))
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('admin.announce.title_help')),

                Forms\Components\Textarea::make('message')
                    ->label(__('admin.announce.message'))
                    ->required()
                    ->rows(3)
                    ->maxLength(2000)
                    ->helperText(__('admin.announce.message_help')),

                Forms\Components\Select::make('icon')
                    ->label(__('admin.announce.icon'))
                    ->options([
                        'megaphone'             => __('admin.announce.icon.megaphone'),
                        'bell'                  => __('admin.announce.icon.bell'),
                        'exclamation-triangle'  => __('admin.announce.icon.warning'),
                        'star'                  => __('admin.announce.icon.star'),
                        'shopping-cart'         => __('admin.announce.icon.cart'),
                        'banknotes'             => __('admin.announce.icon.money'),
                    ])
                    ->default('megaphone')
                    ->required(),

                Forms\Components\TextInput::make('url')
                    ->label(__('admin.announce.detail_url'))
                    ->url()
                    ->nullable()
                    ->helperText(__('admin.announce.url_help')),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();

        // Determine target users
        $query = User::query();
        switch ($data['audience']) {
            case 'customers':
                $query->where('role', 'customer');
                break;
            case 'developers':
                $query->where('role', 'developer');
                break;
            case 'specific':
                $query->whereIn('id', $data['user_ids'] ?? []);
                break;
            // 'all' = no filter
        }

        $users = $query->get();
        $count = 0;

        $notifData = json_encode([
            'title'   => $data['title'],
            'message' => $data['message'],
            'icon'    => $data['icon'],
            'url'     => $data['url'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        foreach ($users as $user) {
            DB::table('notifications')->insert([
                'id'              => (string) Str::uuid(),
                'type'            => 'App\\Notifications\\AdminAnnouncement',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id'   => $user->id,
                'data'            => $notifData,
                'read_at'         => null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $count++;
        }

        Notification::make()
            ->success()
            ->title(__('admin.announce.sent'))
            ->body(str_replace(':count', $count, __('admin.announce.sent_body')))
            ->send();

        // Reset form
        $this->form->fill();
    }
}
