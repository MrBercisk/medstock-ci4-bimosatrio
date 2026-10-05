<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    private UserModel $users;

    public function __construct(?UserModel $users = null)
    {
        $this->users = $users ?? new UserModel();
    }

    /* Return data pengguna jika kredensial benar, null jika salah */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->where('email', $email)->first();

        if ($user === null || ! password_verify($password, $user['password_hash'])) {
            return null;
        }

        session()->regenerate(); // ganti session id setelah login cegah session fixation
        session()->set('user_id', (int) $user['id']); // simpan id user untuk mengetahui user yang sedang login

        return $this->present($user);
    }

    public function logout(): void
    {
        session()->destroy();
    }

    /** Pengguna saat ini, cek dari database (peran tidak dipercaya dari sesi/body). */
    public function currentUser(): ?array
    {
        $id = session()->get('user_id');

        if (! $id) {
            return null;
        }

        $user = $this->users->find($id);

        return $user === null ? null : $this->present($user);
    }

    private function present(array $user): array
    {
        return [
            'id'    => (int) $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
    }
}
