<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Models\ReviewReply;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class ReviewResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('评价');
    }

    public static function getPluralModelLabel(): string
    {
        return __('评价');
    }

    protected static ?string $model = Review::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?int $navigationSort = 2;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.orders');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rating')
                ->label(__('admin.common.rating'))
                ->options(array_combine(range(1, 5), array_map(fn ($n) => str_repeat('★', $n) . str_repeat('☆', 5 - $n), range(1, 5))))
                ->required(),
            Forms\Components\Textarea::make('comment')
                ->label(__('admin.review.comment'))
                ->rows(4)
                ->required(),
            Forms\Components\Toggle::make('approved')
                ->label(__('admin.review.approved'))
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->label(__('admin.review.product')),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label(__('admin.review.reviewer')),
                Tables\Columns\TextColumn::make('rating')->label(__('admin.common.rating'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default     => 'danger',
                    }),
                Tables\Columns\TextColumn::make('comment')
                    ->limit(80),
                Tables\Columns\IconColumn::make('approved')
                    ->boolean()
                    ->label(__('admin.review.approved')),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('replies_count')
                    ->label('回复数')
                    ->counts('replies')
                    ->badge(),
            ])
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('replies'))
            ->filters([
                Tables\Filters\SelectFilter::make('approved')
                    ->options([
                        '1' => __('admin.review.opt.approved'),
                        '0' => __('admin.review.opt.pending'),
                    ]),
                Tables\Filters\SelectFilter::make('rating')
                    ->options(array_combine(range(1, 5), array_map(fn ($n) => str_replace(':n', $n, __('admin.review.stars')), range(1, 5)))),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('approve')
                    ->visible(fn (Review $record) => !$record->approved)
                    ->label(__('admin.review.approve'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Review $record) {
                        $record->update(['approved' => true]);

                        // Recalculate product rating
                        $product = $record->product;
                        $avg = $product->reviews()->approved()->avg('rating');
                        $product->update(['rating' => round($avg ?? 0, 2)]);
                    }),
                Action::make('reject')
                    ->visible(fn (Review $record) => !$record->approved)
                    ->label(__('admin.review.reject'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Review $record) {
                        $record->delete();
                    }),
                Action::make('reply')
                    ->label('回复')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->form([
                        Section::make('已有回复')
                            ->schema(function ($record) {
                                $replies = $record->replies ?? collect();
                                if ($replies->isEmpty()) {
                                    return [
                                        Placeholder::make('no_replies')
                                            ->label('')
                                            ->content('暂无回复'),
                                    ];
                                }
                                return $replies->map(function ($reply) {
                                    return Placeholder::make('reply_' . $reply->id)
                                        ->label(($reply->user?->name ?? 'Unknown') . ' [' . ($reply->reply_type ?? 'platform') . ']')
                                        ->content($reply->content);
                                })->toArray();
                            })
                            ->collapsible()
                            ->collapsed(fn ($record) => $record->replies?->isNotEmpty()),
                        Select::make('review_parent_id')
                            ->label('回复目标评价')
                            ->options(function ($record) {
                                // 列出当前评价可以回复的其他评价(同一产品下)
                                return Review::where('product_id', $record->product_id)
                                    ->where('id', '!=', $record->id)
                                    ->with('user')
                                    ->get()
                                    ->mapWithKeys(fn ($r) => [
                                        $r->id => "#{$r->id} - " . ($r->user?->name ?? 'Unknown') . ': ' . mb_substr($r->comment ?? '', 0, 40),
                                    ]);
                            })
                            ->nullable()
                            ->searchable()
                            ->helperText('留空表示回复当前评价，选择其他评价则为回复指定评价'),
                        Textarea::make('content')
                            ->label('回复内容')
                            ->required()
                            ->rows(3),
                        Select::make('reply_type')
                            ->label('回复身份')
                            ->options([
                                'platform'  => '平台方回复',
                                'developer' => '开发者回复',
                            ])
                            ->default('platform')
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        // 确定实际回复的目标评价ID
                        $targetReviewId = $data['review_parent_id'] ?? $record->id;

                        ReviewReply::create([
                            'review_id'        => $record->id,
                            'review_parent_id' => $data['review_parent_id'] ?? null,
                            'user_id'          => auth()->id(),
                            'content'          => $data['content'],
                            'reply_type'       => $data['reply_type'] ?? 'platform',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('回复成功')
                            ->body($data['review_parent_id'] ? "已回复评价 #{$data['review_parent_id']}" : '已回复当前评价')
                            ->send();
                    })
                    ->modalHeading('回复评价')
                    ->modalSubmitActionLabel('提交回复'),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
