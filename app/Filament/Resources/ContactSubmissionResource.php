<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactSubmissionResource\Pages;
use App\Models\ContactSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactSubmissionResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('联系留言');
    }

    public static function getPluralModelLabel(): string
    {
        return __('联系留言');
    }

    protected static ?string $model = ContactSubmission::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?int $navigationSort = 51;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.marketing');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('email')->disabled(),
            Forms\Components\TextInput::make('other_contact')->label(__('admin.contact.other_contact'))->disabled(),
            Forms\Components\Select::make('status')
                ->options(['new' => __('admin.contact.opt.new'), 'read' => __('admin.contact.opt.read'), 'replied' => __('admin.contact.opt.replied')])
                ->default('new'),
            Forms\Components\Textarea::make('message')
                ->disabled()
                ->columnSpanFull()
                ->rows(6),
            Forms\Components\Textarea::make('admin_reply')
                ->label(__('admin.contact.reply'))
                ->columnSpanFull()
                ->rows(4)
                ->helperText(__('admin.contact.helper.reply')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.common.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')->label(__('admin.common.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('message')->label(__('admin.common.message'))
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->message),
                Tables\Columns\BadgeColumn::make('status')->label(__('admin.common.status'))
                    ->colors([
                        'danger' => 'new',
                        'info' => 'read',
                        'success' => 'replied',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('admin.contact.view_reply')),
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
            'index' => Pages\ListContactSubmissions::route('/'),
            'edit' => Pages\EditContactSubmission::route('/{record}/edit'),
        ];
    }
}
