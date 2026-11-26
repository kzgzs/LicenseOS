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
        $zip = $data['plugin_zip'] ?? null;
        unset($data['plugin_zip']); // 表单临时字段，无对应列
        $record = new (static::getModel());
        $record->forceFill($data)->save();
        if ($zip && in_array($record->type, ProductResource::PACKAGE_TYPES)) {
            ProductResource::registerPackageFromZip($record, $zip);
        }

        return $record;
    }
}
