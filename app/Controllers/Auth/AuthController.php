<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('is_logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login', [
            'title' => 'Sign in',
            'tenantCode' => old('tenant_code', 'QSI'),
            'email' => old('email', ''),
        ]);
    }

    public function authenticate()
    {
        $tenantCode = trim((string) $this->request->getPost('tenant_code'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        if ($tenantCode === '' || $email === '' || $password === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Enter a valid tenant code, email, and password.');
        }

        $result = (new AuthService())->attempt(
            $tenantCode,
            $email,
            $password,
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        if (! $result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        if ($result['must_change_password']) {
            return redirect()->to('/account/password')->with('warning', 'Please change your password before continuing.');
        }

        return redirect()->to('/dashboard')->with('success', 'Welcome back.');
    }

    public function logout()
    {
        (new AuthService())->logout();

        return redirect()->to('/login')->with('success', 'You have been signed out.');
    }
}
