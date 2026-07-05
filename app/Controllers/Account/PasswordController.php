<?php

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogger;

class PasswordController extends BaseController
{
    public function edit()
    {
        return view('account/password', [
            'title' => 'Change password',
        ]);
    }

    public function update()
    {
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return redirect()->back()->with('error', 'All password fields are required.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->with('error', 'The new password confirmation does not match.');
        }

        if (! $this->isStrongPassword($newPassword)) {
            return redirect()->back()->with('error', 'Use at least 12 characters with uppercase, lowercase, number, and symbol.');
        }

        $users = new UserModel();
        $userId = (int) session()->get('user_id');
        $user = $users->find($userId);

        if ($user === null || ! password_verify($currentPassword, $user['password_hash'])) {
            return redirect()->back()->with('error', 'The current password is not correct.');
        }

        $users->update($userId, [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        (new AuditLogger())->record(
            'update',
            'authentication',
            'users',
            $userId,
            null,
            ['password_changed' => true]
        );

        return redirect()->to('/dashboard')->with('success', 'Password changed.');
    }

    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;
    }
}
