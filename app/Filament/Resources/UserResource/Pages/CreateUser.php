<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Remove non-model fields before saving to the users table.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tenant_name'], $data['commission_rate'], $data['status'], $data['stripe_account_id'], $data['alipay_account'], $data['wechat_account']);
        return $data;
    }

    /**
     * After saving user, handle tenant creation for developers.
     */
    protected function afterCreate(): void
    {
        $user = $this->getRecord();
        $data = $this->form->getState();

        if ($user->role === 'developer' && !empty($data['tenant_name'])) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'email' => $user->email,
                'commission_rate' => $data['commission_rate'] ?? 10,
                'status' => $data['status'] ?? 'active',
                'stripe_account_id' => $data['stripe_account_id'] ?? null,
                'alipay_account' => $data['alipay_account'] ?? null,
                'wechat_account' => $data['wechat_account'] ?? null,
            ]);
            $user->update(['tenant_id' => $tenant->id]);
        }
    }
}
