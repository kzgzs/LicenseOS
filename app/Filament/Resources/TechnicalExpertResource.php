<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TechnicalExpertResource\Pages;
use App\Models\TechnicalExpert;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TechnicalExpertResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('技术专家');
    }

    public static function getPluralModelLabel(): string
    {
        return __('技术专家');
    }

    protected static ?string $model = TechnicalExpert::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 54;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.support');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->helperText(__('admin.expert.helper.name')),
            Forms\Components\TextInput::make('specialty')
                ->maxLength(200)
                ->helperText(__('admin.expert.helper.specialty')),
            Forms\Components\Textarea::make('description')
                ->rows(2)
                ->helperText(__('admin.expert.helper.desc')),
            Forms\Components\Select::make('contact_method')
                ->options([
                    'wechat'   => __('admin.expert.opt.wechat'),
                    'qq'       => 'QQ',
                    'telegram' => 'Telegram',
                    'email'    => __('admin.expert.opt.email'),
                    'whatsapp' => 'WhatsApp',
                ])
                ->helperText(__('admin.expert.helper.contact')),
            Forms\Components\TextInput::make('contact_value')
                ->maxLength(200)
                ->helperText(__('admin.expert.helper.contact_value')),
            Forms\Components\FileUpload::make('qr_image')
                ->label(__('admin.expert.qr_image'))
                ->image()
                ->imageResizeMode('cover')
                ->imageCropAspectRatio('1:1')
                ->imageResizeTargetWidth('400')
                ->imageResizeTargetHeight('400')
                ->directory('expert-qr-codes')
                ->maxSize(2048)
                ->helperText(__('admin.expert.helper.qr')),
            Forms\Components\TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText(__('admin.expert.helper.sort')),
            Forms\Components\Toggle::make('active')
                ->default(true)
                ->helperText(__('admin.expert.helper.active')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->label(__('admin.common.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('specialty')->limit(30)->label(__('admin.common.specialty'))->limit(30),
                Tables\Columns\TextColumn::make('contact_method')->badge()->label(__('admin.common.contact_method'))->badge(),
                Tables\Columns\TextColumn::make('contact_value')->limit(20)->label(__('admin.common.contact_value'))->limit(20),
                Tables\Columns\ImageColumn::make('qr_image')->label(__('admin.common.qr_image'))->label(__('admin.expert.qr_code'))->circular(),
                Tables\Columns\IconColumn::make('active')->boolean()->label(__('admin.common.active'))->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label(__('admin.common.sort_order'))->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTechnicalExperts::route('/'),
            'create' => Pages\CreateTechnicalExpert::route('/create'),
            'edit'   => Pages\EditTechnicalExpert::route('/{record}/edit'),
        ];
    }
}
