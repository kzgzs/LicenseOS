<?php
namespace App\Filament\Resources\ProjectContractResource\Pages;
use App\Filament\Resources\ProjectContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectContracts extends ListRecords
{
    protected static string $resource = ProjectContractResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
