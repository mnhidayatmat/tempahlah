<?php

namespace App\Filament\Resources\Tenants;

use App\Filament\Resources\Tenants\Pages\ManageTenants;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business')
                    ->columns(2)
                    ->components([
                        TextInput::make('business_name')->required()->maxLength(255),
                        TextInput::make('slug')->required()->maxLength(255),
                        TextInput::make('business_email')->email()->maxLength(255),
                        TextInput::make('business_phone')->tel()->maxLength(20),
                        TextInput::make('ssm_number')->label('SSM number')->maxLength(40),
                        TextInput::make('motac_license')->label('MOTAC licence')->maxLength(60),
                    ]),
                Section::make('Operations')
                    ->columns(2)
                    ->components([
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),
                        Select::make('kyc_status')
                            ->label('KYC')
                            ->options([
                                'pending' => 'Pending',
                                'submitted' => 'Submitted',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending'),
                        Toggle::make('sst_registered')->label('SST registered')->inline(false),
                        TextInput::make('sst_rate')->label('SST rate (decimal)')->numeric(),
                        Select::make('default_locale')
                            ->options(['ms' => 'Bahasa Melayu', 'en' => 'English'])
                            ->default('ms'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')->searchable()->sortable()->limit(40),
                TextColumn::make('slug')->toggleable()->copyable()->copyMessage('Slug copied'),
                TextColumn::make('subscription.plan')
                    ->label('Plan')
                    ->badge()
                    ->colors(['gray' => 'free', 'success' => 'paid'])
                    ->placeholder('—'),
                TextColumn::make('kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info'    => 'submitted',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger'  => 'suspended',
                    ]),
                IconColumn::make('sst_registered')
                    ->label('SST')
                    ->boolean(),
                TextColumn::make('motac_license')->label('MOTAC')->limit(20)->toggleable(),
                TextColumn::make('created_at')->date()->sortable()->label('Joined'),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'paused' => 'Paused',
                    'suspended' => 'Suspended',
                ]),
                SelectFilter::make('kyc_status')->options([
                    'pending' => 'Pending',
                    'submitted' => 'Submitted',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTenants::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
