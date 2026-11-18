<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Dapatkan nama role pengguna (tersinkronisasi dinamis dengan portal Mekar Jaya & SIR-IQOS).
     */
    public function getRoleAttribute(): string
    {
        // 1. Ambil role resmi dari mekarjaya_db (Portal Superapp) jika terhubung
        try {
            $portalRole = DB::connection('mysql')
                ->table('mekarjaya_db.users as u')
                ->join('mekarjaya_db.model_has_roles as mhr', 'u.id', '=', 'mhr.model_id')
                ->join('mekarjaya_db.roles as r', 'mhr.role_id', '=', 'r.id')
                ->where('u.email', $this->email)
                ->value('r.name');

            if ($portalRole) {
                return strtoupper(str_replace(['_', '-'], ' ', $portalRole));
            }
        } catch (\Throwable) {
            // Fallback jika mekarjaya_db tidak dapat diakses
        }

        // 2. Fallback berdasarkan email dan nama akun
        $lowerEmail = strtolower($this->email);
        $lowerName = strtolower($this->name);

        if (str_contains($lowerEmail, 'superadmin') || str_contains($lowerName, 'super admin') || str_contains($lowerName, 'super administrator')) {
            return 'SUPER ADMIN';
        }

        if (str_contains($lowerEmail, 'admin') || str_contains($lowerName, 'admin')) {
            return 'ADMINISTRATOR';
        }

        return 'HOLDING FINANCE';
    }
}
