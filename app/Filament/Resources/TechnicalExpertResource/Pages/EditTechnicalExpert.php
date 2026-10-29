<?php

namespace App\Filament\Resources\TechnicalExpertResource\Pages;

use App\Filament\Resources\TechnicalExpertResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicalExpert extends EditRecord
{
    protected static string $resource = TechnicalExpertResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
