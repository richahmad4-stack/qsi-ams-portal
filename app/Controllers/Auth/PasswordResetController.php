<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\PasswordResetTokenModel;
use App\Models\UserModel;
use App\Services\AuditLogger;
use App\Services\PasswordPolicy;
use Config\Database;

class PasswordResetController extends BaseController
{
    private const TOKEN_TTL_MINUTES = 60;

    public function request()
    {
        return view('auth/forgot_password', [
            'title' => 'Reset password',
            'tenantCode' => old('tenant_code', 'QSI'),
            'email' => old('email', ''),
        ]);
    }

    public function send()
    {
        $tenantCode = strtoupper(trim((string) $this->request->getPost('tenant_code')));
        $email = strtolower(trim((string) $this->request->getPost('email')));

        if ($tenantCode === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return redirect()->back()->withInput()->with('error', 'Enter a valid tenant code and email.');
        }

        $users = new UserModel();
        $user = $users->findForLogin($tenantCode, $email);
        if ($user !== null && (string) $user['status'] === 'active') {
            $link = $this->createResetLink((int) $user['id']);
            $this->deliverResetLink($email, (string) $user['full_name'], $link);
        }

        return redirect()->to('/login')->with('success', 'If the account exists, a password reset link has been sent.');
    }

    public function edit()
    {
        $selector = trim((string) $this->request->getGet('selector'));
        $token = trim((string) $this->request->getGet('token'));

        if (! $this->validToken($selector, $token)) {
            return redirect()->to('/login')->with('error', 'The password reset link is invalid or expired.');
        }

        return view('auth/reset_password', [
            'title' => 'Choose new password',
            'selector' => $selector,
            'token' => $token,
        ]);
    }

    public function update()
    {
        $selector = trim((string) $this->request->getPost('selector'));
        $token = trim((string) $this->request->getPost('token'));
        $password = (string) $this->request->getPost('password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');
        $record = $this->validToken($selector, $token);

        if ($record === null) {
            return redirect()->to('/login')->with('error', 'The password reset link is invalid or expired.');
        }

        if ($password !== $confirmPassword) {
            return redirect()->back()->withInput()->with('error', 'The password confirmation does not match.');
        }

        $policy = new PasswordPolicy();
        if (! $policy->isStrong($password)) {
            return redirect()->back()->withInput()->with('error', PasswordPolicy::MESSAGE);
        }

        $users = new UserModel();
        $users->update((int) $record['user_id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);

        (new PasswordResetTokenModel())->update((int) $record['id'], [
            'used_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogger())->record(
            'update',
            'authentication',
            'users',
            (int) $record['user_id'],
            null,
            ['password_reset_completed' => true]
        );

        return redirect()->to('/login')->with('success', 'Password reset completed. Please sign in.');
    }

    private function createResetLink(int $userId): string
    {
        $selector = bin2hex(random_bytes(9));
        $token = bin2hex(random_bytes(32));
        $tokens = new PasswordResetTokenModel();
        Database::connect()->table('password_reset_tokens')
            ->where('user_id', $userId)
            ->where('used_at', null)
            ->update([
            'used_at' => date('Y-m-d H:i:s'),
        ]);

        $tokens->insert([
            'user_id' => $userId,
            'selector' => $selector,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + (self::TOKEN_TTL_MINUTES * 60)),
            'used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return site_url('reset-password') . '?' . http_build_query([
            'selector' => $selector,
            'token' => $token,
        ]);
    }

    private function validToken(string $selector, string $token): ?array
    {
        if ($selector === '' || $token === '') {
            return null;
        }

        $record = (new PasswordResetTokenModel())
            ->where('selector', $selector)
            ->where('used_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();

        if ($record === null || ! hash_equals((string) $record['token_hash'], hash('sha256', $token))) {
            return null;
        }

        return $record;
    }

    private function deliverResetLink(string $emailAddress, string $name, string $link): void
    {
        $emailEnabled = filter_var((string) env('AMS_EMAIL_NOTIFICATIONS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        $smtpHost = trim((string) env('email.SMTPHost', ''));

        if ($emailEnabled && $smtpHost !== '') {
            $email = service('email');
            $email->setTo($emailAddress);
            $email->setSubject('QSI AMS password reset');
            $email->setMessage(
                '<p>Dear ' . esc($name) . ',</p>'
                . '<p>Use this link to reset your QSI AMS password. The link expires in 60 minutes.</p>'
                . '<p><a href="' . esc($link) . '">' . esc($link) . '</a></p>'
            );
            $email->send(false);
        }

        if (ENVIRONMENT !== 'production') {
            log_message('info', 'Password reset link for {email}: {link}', [
                'email' => $emailAddress,
                'link' => $link,
            ]);
        }
    }
}
