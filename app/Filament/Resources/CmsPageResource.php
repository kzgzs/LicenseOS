<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsPageResource\Pages;
use App\Models\CmsPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CmsPageResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('CMS页面');
    }

    public static function getPluralModelLabel(): string
    {
        return __('CMS页面');
    }

    protected static ?string $model = CmsPage::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 50;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.marketing');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->reactive()
                ->afterStateUpdated(fn (Forms\Set $set, ?string $state) =>
                    $set('slug', \Illuminate\Support\Str::slug($state))
                ),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->helperText(__('admin.cms.helper.slug')),
            Forms\Components\Select::make('status')
                ->options(['draft' => __('admin.product.opt.draft'), 'published' => __('admin.product.opt.published')])
                ->default('published'),
            Forms\Components\Tabs::make('content_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.cms.tab.editor'))
                        ->schema([
                            Forms\Components\RichEditor::make('content')
                                ->required()
                                ->toolbarButtons([
                                    'attachFiles',
                                    'blockquote',
                                    'bold',
                                    'bulletList',
                                    'codeBlock',
                                    'h2',
                                    'h3',
                                    'italic',
                                    'link',
                                    'orderedList',
                                    'redo',
                                    'strike',
                                    'underline',
                                    'undo',
                                ]),
                        ]),
                    Forms\Components\Tabs\Tab::make(__('admin.cms.tab.html'))
                        ->schema([
                            Forms\Components\Textarea::make('content')
                                ->required()
                                ->rows(20)
                                ->helperText(__('admin.cms.helper.html'))
                                ->formatStateUsing(fn ($state) => $state ?? '')
                                ->dehydrateStateUsing(function ($state) {
                                    // 纯文本（不含 HTML 标签）自动转换为 HTML 段落，保留换行
                                    if ($state && strip_tags($state) === $state) {
                                        $paragraphs = preg_split('/\n\s*\n/', trim($state));
                                        if (count($paragraphs) > 1) {
                                            $state = '<p>' . implode('</p><p>', array_map(fn($p) => nl2br(trim($p)), $paragraphs)) . '</p>';
                                        } else {
                                            $state = '<p>' . nl2br(trim($state)) . '</p>';
                                        }
                                    }
                                    return $state;
                                })
                                ->dehydrated(true),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
            Forms\Components\Textarea::make('meta_description')
                ->label(__('admin.cms.meta_description'))
                ->maxLength(500)
                ->columnSpanFull()
                ->helperText(__('admin.cms.helper.meta')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('admin.common.title'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')->label(__('admin.common.slug'))
                    ->label(__('admin.cms.url'))
                    ->formatStateUsing(fn ($state) => "/page/{$state}")
                    ->color('gray'),
                Tables\Columns\BadgeColumn::make('status')->label(__('admin.common.status'))
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                    ]),
                Tables\Columns\TextColumn::make('updated_at')->label(__('admin.common.updated_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('preview')
                    ->url(fn (CmsPage $record) => url("/page/{$record->slug}"))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }
}
