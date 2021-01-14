<?php

namespace App\Actions;

use App\Exceptions\CouldNotRenewLicenseForPurchase;
use App\Models\License;
use App\Models\Purchasable;
use App\Models\Purchase;
use App\Models\User;

class HandlePurchaseLicensingAction
{
    protected CreateLicenseAction $createLicenseAction;

    public function __construct(CreateLicenseAction $createLicenseAction)
    {
        $this->createLicenseAction = $createLicenseAction;
    }

    public function execute(Purchase $purchase): Purchase
    {
        if (! $purchase->purchasable->requires_license) {
            return $purchase;
        }

        if ($purchase->purchasable->isRenewal()) {
            $this->handleRenewal($purchase);
        }

        foreach (range(1, $purchase->quantity) as $i) {
            $this->createLicenseAction->execute($purchase->user, $purchase->purchasable);
        }

        return $purchase;
    }

    protected function createOrRenewLicense(User $user, Purchasable $purchasable, bool $isRenewal): License
    {
        $license = License::query()
            ->where('purchasable_id', $purchasable->id)
            ->where('user_id', $user->id)
            ->first();

        if ($license !== null && $isRenewal) {
            return $license->renew();
        }

        return $this->createLicenseAction->execute($user, $purchasable);
    }

    protected function handleRenewal(Purchase $purchase): void
    {
        $license = $purchase->wasMadeForLicense();

        if (! $license) {
            throw CouldNotRenewLicenseForPurchase::make($purchase);
        }

        $license->renew();
    }
}
