<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\TicketReply;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TicketResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('工单');
    }

    public static function getPluralModelLabel(): string
    {
        return __('工单');
    }

    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';
    protected static ?int $navigationSort = 53;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.support');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('subject')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('message')->disabled()->rows(4)->columnSpanFull(),
            Forms\Components\Select::make('status')
                ->options([
                    'open' => __('admin.ticket.opt.open'), 'in_progress' => __('admin.ticket.opt.in_progress'),
                    'resolved' => __('admin.ticket.opt.resolved'), 'closed' => __('admin.ticket.opt.closed'),
                ])->required(),
            Forms\Components\Select::make('priority')
                ->options([
                    'low' => __('admin.ticket.opt.low'), 'medium' => __('admin.ticket.opt.medium'),
                    'high' => __('admin.ticket.opt.high'), 'urgent' => __('admin.ticket.opt.urgent'),
                ])->required(),
            Forms\Components\Section::make(__('admin.ticket.admin_reply'))
                ->schema([
                    Forms\Components\Textarea::make('admin_reply')
                        ->label(__('admin.ticket.reply_message'))
                        ->rows(4)
                        ->helperText(__('admin.ticket.helper.reply')),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable()->label('#'),
                Tables\Columns\TextColumn::make('user.name')->searchable()->label(__('admin.ticket.user')),
                Tables\Columns\TextColumn::make('subject')->limit(40)->searchable()->label(__('admin.common.subject'))->limit(40)->searchable(),
                Tables\Columns\BadgeColumn::make('status')->label(__('admin.common.status'))
                    ->colors([
                        'primary' => 'open',
                        'warning' => 'in_progress',
                        'success' => 'resolved',
                        'gray'    => 'closed',
                    ]),
                Tables\Columns\BadgeColumn::make('priority')->label(__('admin.common.priority'))
                    ->colors([
                        'gray'    => 'low',
                        'info'    => 'medium',
                        'warning' => 'high',
                        'danger'  => 'urgent',
                    ]),
                Tables\Columns\BadgeColumn::make('category')->label(__('admin.common.category'))
                    ->colors([
                        'gray'    => 'general',
                        'success' => 'billing',
                        'info'    => 'technical',
                        'warning' => 'account',
                    ]),
                Tables\Columns\TextColumn::make('replies_count')->label(__('admin.common.replies_count'))
                    ->counts('replies')
                    ->label(__('admin.ticket.replies'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => __('admin.ticket.opt.open'), 'in_progress' => __('admin.ticket.opt.in_progress'),
                        'resolved' => __('admin.ticket.opt.resolved'), 'closed' => __('admin.ticket.opt.closed'),
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => __('admin.ticket.opt.low'), 'medium' => __('admin.ticket.opt.medium'),
                        'high' => __('admin.ticket.opt.high'), 'urgent' => __('admin.ticket.opt.urgent'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('admin.ticket.view_reply')),
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
            'index' => Pages\ListTickets::route('/'),
            'edit'  => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
