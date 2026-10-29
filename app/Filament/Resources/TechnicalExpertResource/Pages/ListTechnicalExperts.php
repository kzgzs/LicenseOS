<?php

namespace App\Filament\Resources\TechnicalExpertResource\Pages;

use App\Filament\Resources\TechnicalExpertResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicalExperts extends ListRecords
{
    protected static string $resource = TechnicalExpertResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
