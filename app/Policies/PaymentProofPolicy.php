<?php

namespace App\Policies;

use App\PaymentProof;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Helpers\RoleHelper;

class PaymentProofPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the payment proof.
     * Clients can delete their own proofs only while pending. Admins can delete any.
     */
    public function delete(User $user, PaymentProof $paymentProof)
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        if (RoleHelper::hasRole($user, 'client')) {
            $clientId = optional($user->client)->id;
            return $clientId && $paymentProof->client_id === $clientId && $paymentProof->status === 'pending';
        }

        return false;
    }

    /**
     * Verify or reject a payment proof - admins only, and only when pending.
     */
    public function verify(User $user, PaymentProof $paymentProof)
    {
        return RoleHelper::hasRole($user, 'admin') && $paymentProof->status === 'pending';
    }
}