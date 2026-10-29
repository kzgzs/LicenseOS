<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Load tenant data into form fields before rendering.
     * Re-fills with explicit user + tenant data to prevent auth leakage and clear password hash.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = $this->getRecord();
        if ($user instanceof User) {
            $data = array_merge(
                $user->only(['name', 'email', 'role']),
                ['password' => '']
            );
            if ($user->tenant) {
                $data['tenant_name'] = $user->tenant->name;
                $data['commission_rate'] = $user->tenant->commission_rate;
                $data['status'] = $user->tenant->status;
                $data['stripe_account_id'] = $user->tenant->stripe_account_id;
                $data['alipay_account'] = $user->tenant->alipay_account;
                $data['wechat_account'] = $user->tenant->wechat_account;
            }
            $this->form->fill($data);
        }
    }

    /**
     * Remove non-model fields before saving to the users table.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tenant_name'], $data['commission_rate'], $data['status'], $data['stripe_account_id'], $data['alipay_account'], $data['wechat_account']);
        return $data;
    }

    /**
     * After saving user, handle tenant create/update.
     */
    protected function afterSave(): void
    {
        $user = $this->getRecord();
        $data = $this->form->getState();

        if ($user->role === 'developer') {
            $tenantName = $data['tenant_name'] ?? null;
            $commissionRate = $data['commission_rate'] ?? 10;
            $status = $data['status'] ?? 'active';
            $stripeAccountId = $data['stripe_account_id'] ?? null;
            $alipayAccount = $data['alipay_account'] ?? null;
            $wechatAccount = $data['wechat_account'] ?? null;

            if (!$user->tenant_id && $tenantName) {
                // Create new tenant
                $tenant = Tenant::create([
                    'name' => $tenantName,
                    'email' => $user->email,
                    'commission_rate' => $commissionRate,
                    'status' => $status,
                    'stripe_account_id' => $stripeAccountId,
                    'alipay_account' => $alipayAccount,
                    'wechat_account' => $wechatAccount,
                ]);
                $user->update(['tenant_id' => $tenant->id]);
            } elseif ($user->tenant_id) {
                // Update existing tenant
                $tenant = Tenant::find($user->tenant_id);
                if ($tenant) {
                    $tenant->update([
                        'name' => $tenantName ?: $tenant->name,
                        'email' => $user->email,
                        'commission_rate' => $commissionRate,
                        'status' => $status,
                        'stripe_account_id' => $stripeAccountId,
                        'alipay_account' => $alipayAccount,
                        'wechat_account' => $wechatAccount,
                    ]);
                }
            }
        } else {
            // Non-developer: clear tenant binding
            if ($user->tenant_id) {
                $user->update(['tenant_id' => null]);
            }
        }
    }
}
