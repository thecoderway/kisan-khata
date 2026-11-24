<?php

namespace App\Modules\Customers\Application;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveCustomer
{
    private const AUDITED_FIELDS = [
        'name_original',
        'name_original_language',
        'name_hi',
        'name_en',
        'mobile',
        'state_code',
        'district_code',
        'subdistrict_code',
        'village_code',
        'address_original',
        'address_original_language',
        'address_hi',
        'address_en',
        'notes',
    ];

    public function create(User $actor, array $data, ?string $ipAddress, ?string $userAgent): Customer
    {
        return DB::transaction(function () use ($actor, $data, $ipAddress, $userAgent): Customer {
            $customer = new Customer;
            $customer->fill($data);
            $customer->forceFill([
                'created_by' => $actor->getKey(),
                'status' => Customer::STATUS_ACTIVE,
            ]);
            $customer->save();

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'customer.created',
                'entity_type' => 'customer',
                'entity_id' => $customer->getKey(),
                'new_values' => $customer->only(self::AUDITED_FIELDS),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $customer;
        });
    }

    public function update(
        User $actor,
        Customer $customer,
        array $data,
        ?string $ipAddress,
        ?string $userAgent,
    ): Customer {
        return DB::transaction(function () use ($actor, $customer, $data, $ipAddress, $userAgent): Customer {
            $oldValues = $customer->only(self::AUDITED_FIELDS);
            $customer->fill($data);

            if (array_key_exists('state_code', $data) && $data['state_code'] !== $oldValues['state_code']) {
                $customer->fill([
                    'district_code' => $data['district_code'] ?? null,
                    'subdistrict_code' => $data['subdistrict_code'] ?? null,
                    'village_code' => $data['village_code'] ?? null,
                ]);
            } elseif (array_key_exists('district_code', $data) && $data['district_code'] !== $oldValues['district_code']) {
                $customer->fill([
                    'subdistrict_code' => $data['subdistrict_code'] ?? null,
                    'village_code' => $data['village_code'] ?? null,
                ]);
            } elseif (array_key_exists('subdistrict_code', $data) && $data['subdistrict_code'] !== $oldValues['subdistrict_code']) {
                $customer->fill(['village_code' => $data['village_code'] ?? null]);
            }

            $dirtyFields = array_keys(array_intersect_key(
                $customer->getDirty(),
                array_flip(self::AUDITED_FIELDS),
            ));

            if ($dirtyFields === []) {
                return $customer;
            }

            $newValues = $customer->only($dirtyFields);
            $customer->save();

            AuditLog::create([
                'user_id' => $actor->getKey(),
                'action' => 'customer.updated',
                'entity_type' => 'customer',
                'entity_id' => $customer->getKey(),
                'old_values' => array_intersect_key($oldValues, array_flip($dirtyFields)),
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return $customer->refresh();
        });
    }
}
