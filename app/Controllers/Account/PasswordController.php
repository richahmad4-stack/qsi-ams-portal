<?php

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;

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

        $policy = new PasswordPolicy();
        if (! $policy->isStrong($newPassword)) {
            return redirect()->back()->with('error', PasswordPolicy::MESSAGE);
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

}
