<?php

namespace App\Filament\Resources\UpdatePackageResource\Pages;

use App\Filament\Resources\UpdatePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUpdatePackages extends ListRecords
{
    protected static string $resource = UpdatePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
