<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Helpers\RoleHelper;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\User;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Perform a case-insensitive email lookup before attempting login.
     * This prevents login failures caused by database collations that treat
     * email comparisons as case-sensitive (e.g., utf8mb4_bin) and does not
     * modify any user data.
     */
    protected function attemptLogin(Request $request)
    {
        $email = strtolower(trim($request->input($this->username())));

        // Case-insensitive email match without altering stored data
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return false;
        }

        $password = (string) $request->input('password');
        if (! Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        $remember = (bool) $request->filled('remember');
        $this->guard()->login($user, $remember);
        return true;
    }

    public function authenticated(Request $request, $user)
    {
        //exit("testing");
        if (!$user->enabled) {
            auth()->logout();
            return back()->withErrors(['Your account is not enabled.']);
        }

        // Use optimized RoleHelper to check for 'new-agent' role
        if (RoleHelper::hasRole($user, 'new-agent')) {
            return redirect('/agent/profile/create');
        }
        // Redirect users to role dashboards for improved UX
        if (RoleHelper::hasRole($user, 'super-admin') || RoleHelper::hasRole($user, 'admin')) {
            return redirect('/admin/dashboard');
        }

        if (RoleHelper::hasRole($user, 'agent')) {
            return redirect('/agent/dashboard');
        }

        if (RoleHelper::hasRole($user, 'client')) {
            return redirect('/client/dashboard');
        }

        // Fallback
        return redirect('/interpreter-jobs');
    }
}