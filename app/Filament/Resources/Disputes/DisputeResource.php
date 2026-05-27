<?php

namespace App\Filament\Resources\Disputes;

use App\Filament\Resources\Disputes\Pages\ManageDisputes;
use App\Models\Dispute;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Disputes';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Case')
                    ->columns(2)
                    ->components([
                        Select::make('booking_id')
                            ->label('Booking')
                            ->relationship('booking', 'reference')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('guest_user_id')
                            ->label('Guest')
                            ->relationship('guest', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('reason')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('amount_claimed')
                            ->label('Amount claimed (RM)')
                            ->numeric()
                            ->prefix('RM'),
                        Select::make('status')
                            ->options([
                                Dispute::STATUS_OPEN => 'Open',
                                Dispute::STATUS_INVESTIGATING => 'Investigating',
                                Dispute::STATUS_RESOLVED => 'Resolved',
                                Dispute::STATUS_CLOSED => 'Closed',
                            ])
                            ->default(Dispute::STATUS_OPEN)
                            ->required(),
                    ]),
                Section::make('Description')
                    ->components([
                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Resolution')
                    ->columns(2)
                    ->components([
                        Select::make('assigned_admin_id')
                            ->label('Assigned admin')
                            ->relationship('assignedAdmin', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('resolution_amount')
                            ->label('Resolution amount (RM)')
                            ->numeric()
                            ->prefix('RM'),
                        Textarea::make('resolution')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant.business_name')
                    ->label('Tenant')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('reason')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('amount_claimed')
                    ->money('MYR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => Dispute::STATUS_OPEN,
                        'info'    => Dispute::STATUS_INVESTIGATING,
                        'success' => Dispute::STATUS_RESOLVED,
                        'gray'    => Dispute::STATUS_CLOSED,
                    ])
                    ->sortable(),
                TextColumn::make('assignedAdmin.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Opened')
                    ->date()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Dispute::STATUS_OPEN => 'Open',
                        Dispute::STATUS_INVESTIGATING => 'Investigating',
                        Dispute::STATUS_RESOLVED => 'Resolved',
                        Dispute::STATUS_CLOSED => 'Closed',
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
            'index' => ManageDisputes::route('/'),
        ];
    }
}
