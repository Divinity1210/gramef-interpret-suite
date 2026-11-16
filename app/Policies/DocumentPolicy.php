<?php

namespace App\Policies;

use App\User;
use App\Document;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Helpers\RoleHelper;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function delete(User $user, Document $document)
    {
        if (RoleHelper::hasRole($user, 'admin')) {
            return true;
        }

        if (RoleHelper::hasRole($user, 'client')) {
            return false;
        }

        if (RoleHelper::hasRole($user, 'agent')) {
            return false;
        }

        if ($user->agent->is($document->documentable)) {
            return true;
        }

        return false;
    }
}
