<?php

namespace App\Filament\Resources\GuestBlacklistEntries\Pages;

use App\Filament\Resources\GuestBlacklistEntries\GuestBlacklistEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuestBlacklistEntries extends ManageRecords
{
    protected static string $resource = GuestBlacklistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
