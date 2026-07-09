<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription')
                    ->columns(2)
                    ->components([
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('plan')
                            ->options([
                                Subscription::PLAN_FREE => 'Free',
                                Subscription::PLAN_PAID => 'Paid (RM 49/mo)',
                            ])
                            ->default(Subscription::PLAN_FREE)
                            ->required(),
                        Select::make('status')
                            ->options([
                                Subscription::STATUS_TRIALING => 'Trialing',
                                Subscription::STATUS_ACTIVE => 'Active',
                                Subscription::STATUS_PAST_DUE => 'Past due',
                                Subscription::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->required(),
                        Select::make('billing_method')
                            ->options([
                                'manual' => 'Manual invoice',
                                'billplz' => 'Billplz (recurring)',
                            ])
                            ->default('manual'),
                        TextInput::make('monthly_amount')->numeric()->prefix('RM')->default(49),
                        TextInput::make('currency')->default('MYR')->maxLength(3),
                    ]),
                Section::make('Period')
                    ->columns(2)
                    ->components([
                        DateTimePicker::make('trial_ends_at'),
                        DateTimePicker::make('trial_used_at')
                            ->helperText('Set once the tenant starts their free trial. Clearing it lets them trial again.'),
                        DateTimePicker::make('current_period_start'),
                        DateTimePicker::make('current_period_end'),
                        DateTimePicker::make('grace_ends_at')
                            ->helperText('While set and in the future, a past_due tenant keeps its paid features.'),
                        DateTimePicker::make('cancelled_at'),
                        DateTimePicker::make('comped_at')
                            ->helperText('Complimentary Pro. Never billed, never downgraded, excluded from MRR.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.business_name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->limit(35),
                TextColumn::make('plan')
                    ->badge()
                    ->colors([
                        'gray' => Subscription::PLAN_FREE,
                        'success' => Subscription::PLAN_PAID,
                    ])
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'info' => Subscription::STATUS_TRIALING,
                        'success' => Subscription::STATUS_ACTIVE,
                        'warning' => Subscription::STATUS_PAST_DUE,
                        'danger' => Subscription::STATUS_CANCELLED,
                    ]),
                TextColumn::make('monthly_amount')->money('MYR')->sortable(),
                TextColumn::make('billing_method')->toggleable(),
                TextColumn::make('trial_ends_at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('current_period_end')
                    ->label('Renews')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')->date()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')->options([
                    Subscription::PLAN_FREE => 'Free',
                    Subscription::PLAN_PAID => 'Paid',
                ]),
                SelectFilter::make('status')->options([
                    Subscription::STATUS_TRIALING => 'Trialing',
                    Subscription::STATUS_ACTIVE => 'Active',
                    Subscription::STATUS_PAST_DUE => 'Past due',
                    Subscription::STATUS_CANCELLED => 'Cancelled',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}
