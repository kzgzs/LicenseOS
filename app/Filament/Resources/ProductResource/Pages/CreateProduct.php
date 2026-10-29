<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Apply shared save rules (free toggle, trial fallback, readme_sections sync).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ProductResource::applyProductSaveRules($data);
    }

    /**
     * forceFill so non-fillable-but-real columns (e.g. license_protection_enabled)
     * are persisted; plain Model::create() would silently drop them.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new (static::getModel());
        $record->forceFill($data)->save();

        return $record;
    }
}
