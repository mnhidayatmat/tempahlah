<?php

namespace App\Filament\Resources\GuestBlacklistEntries;

use App\Filament\Resources\GuestBlacklistEntries\Pages\ManageGuestBlacklistEntries;
use App\Models\GuestBlacklistEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GuestBlacklistEntryResource extends Resource
{
    protected static ?string $model = GuestBlacklistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Guest blacklist';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report')
                    ->columns(2)
                    ->components([
                        Select::make('guest_user_id')
                            ->label('Guest')
                            ->relationship('guest', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('reported_by_tenant_id')
                            ->label('Reported by tenant')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('booking_id')
                            ->label('Booking (optional)')
                            ->relationship('booking', 'reference')
                            ->searchable()
                            ->preload(),
                        Select::make('severity')
                            ->options([
                                GuestBlacklistEntry::SEVERITY_NOTE => 'Note',
                                GuestBlacklistEntry::SEVERITY_WARNING => 'Warning',
                                GuestBlacklistEntry::SEVERITY_BLACKLIST => 'Blacklist',
                            ])
                            ->default(GuestBlacklistEntry::SEVERITY_NOTE)
                            ->required(),
                        TextInput::make('reason_code')->maxLength(60),
                    ]),
                Section::make('Description')
                    ->components([
                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Review')
                    ->columns(2)
                    ->components([
                        Select::make('review_status')
                            ->options([
                                GuestBlacklistEntry::STATUS_PENDING => 'Pending',
                                GuestBlacklistEntry::STATUS_APPROVED => 'Approved',
                                GuestBlacklistEntry::STATUS_REJECTED => 'Rejected',
                                GuestBlacklistEntry::STATUS_OVERTURNED => 'Overturned',
                            ])
                            ->default(GuestBlacklistEntry::STATUS_PENDING)
                            ->required(),
                        Select::make('reviewed_by_admin_id')
                            ->label('Reviewed by')
                            ->relationship('reviewedBy', 'name')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('reviewed_at'),
                        Textarea::make('admin_notes')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Appeal')
                    ->columns(2)
                    ->components([
                        Toggle::make('appealed')->inline(false),
                        DateTimePicker::make('appealed_at'),
                        Textarea::make('appeal_message')->rows(2)->columnSpanFull(),
                        TextInput::make('appeal_outcome')->maxLength(255)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.name')->label('Guest')->searchable()->limit(28),
                TextColumn::make('tenant.business_name')->label('Reported by')->searchable()->limit(28),
                TextColumn::make('severity')
                    ->badge()
                    ->colors([
                        'gray' => GuestBlacklistEntry::SEVERITY_NOTE,
                        'warning' => GuestBlacklistEntry::SEVERITY_WARNING,
                        'danger' => GuestBlacklistEntry::SEVERITY_BLACKLIST,
                    ]),
                TextColumn::make('reason_code')->limit(20)->toggleable(),
                TextColumn::make('review_status')
                    ->label('Review')
                    ->badge()
                    ->colors([
                        'warning' => GuestBlacklistEntry::STATUS_PENDING,
                        'success' => GuestBlacklistEntry::STATUS_APPROVED,
                        'danger'  => GuestBlacklistEntry::STATUS_REJECTED,
                        'info'    => GuestBlacklistEntry::STATUS_OVERTURNED,
                    ]),
                IconColumn::make('appealed')
                    ->label('Appealed')
                    ->boolean(),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->date()->sortable()->label('Reported'),
            ])
            ->filters([
                SelectFilter::make('severity')->options([
                    GuestBlacklistEntry::SEVERITY_NOTE => 'Note',
                    GuestBlacklistEntry::SEVERITY_WARNING => 'Warning',
                    GuestBlacklistEntry::SEVERITY_BLACKLIST => 'Blacklist',
                ]),
                SelectFilter::make('review_status')->options([
                    GuestBlacklistEntry::STATUS_PENDING => 'Pending',
                    GuestBlacklistEntry::STATUS_APPROVED => 'Approved',
                    GuestBlacklistEntry::STATUS_REJECTED => 'Rejected',
                    GuestBlacklistEntry::STATUS_OVERTURNED => 'Overturned',
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
            'index' => ManageGuestBlacklistEntries::route('/'),
        ];
    }
}
