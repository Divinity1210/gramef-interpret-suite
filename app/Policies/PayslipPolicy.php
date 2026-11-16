<?php

namespace App\Policies;

use App\Payslip;
use App\User;
use App\Helpers\RoleHelper;

class PayslipPolicy
{
    /**
     * Determine whether the user can view the payslip.
     */
    public function view(?User $user, Payslip $payslip): bool
    {
        if (!$user) {
            return false;
        }

        // Admins can view all payslips
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        // Agents can view their own payslips only
        if (RoleHelper::hasRole($user, 'agent')) {
            return (int) data_get($payslip, 'agent.user_id') === (int) $user->id;
        }

        // Default deny for other roles
        return false;
    }

    /**
     * Determine whether the user can update/edit the payslip.
     */
    public function update(User $user, Payslip $payslip): bool
    {
        // Only admins can update payslips, and only when pending
        return RoleHelper::hasRole($user, 'admin') && $payslip->status === 'pending';
    }

    /**
     * Determine whether the user can approve the payslip.
     */
    public function approve(User $user, Payslip $payslip): bool
    {
        return RoleHelper::hasRole($user, 'admin') && $payslip->status === 'pending';
    }

    /**
     * Determine whether the user can recall an approved payslip.
     */
    public function recall(User $user, Payslip $payslip): bool
    {
        $invoicePaid = strtolower((string) (optional(optional($payslip->invoice))->status_label ?? optional(optional($payslip->invoice))->status ?? '')) === 'paid';
        return RoleHelper::hasRole($user, 'admin') && $payslip->status === 'approved' && !$invoicePaid && $payslip->paid_at === null;
    }

    public function markPaid(User $user, Payslip $payslip): bool
    {
        return RoleHelper::hasRole($user, 'admin') && $payslip->status === 'approved' && $payslip->paid_at === null;
    }

    public function delete(User $user, Payslip $payslip): bool
    {
        return RoleHelper::hasRole($user, 'admin') && $payslip->status === 'pending';
    }
}
