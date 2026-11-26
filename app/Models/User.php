<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const DEFAULT_EMAIL_ASSIGNMENTS = [
        'ferdinand.winoto@gmail.com' => ['abc', 'aqua', 'ccod', 'fmcg', 'xtrans'],
        'tasya.laisafi1@mekar-jaya.com' => ['onbold', 'cocacola'],
        'vionadelian@gmail.com' => ['sangria', 'mohala', 'iqos'],
        'fa.vionaa@gmail.com' => ['sangria', 'mohala', 'iqos'],
        'fransiskalia21@gmail.com' => ['spbu', 'kandang'],
        'paskaliaspbu89@gmail.com' => ['spbu', 'kandang'],
    ];

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

    public function businessUnits(): HasMany
    {
        return $this->hasMany(UserBusinessUnit::class, 'user_id');
    }

    /**
     * Cek apakah user memiliki hak akses tak terbatas (Direktur, Superadmin, Kadiv FA, atau Holding default).
     */
    public function isUnrestrictedFinance(): bool
    {
        $lowerEmail = strtolower($this->email);

        // Jika email terdaftar di mapping pembagian unit atau memiliki pembatasan pivot, user berstatus restricted
        if (isset(self::DEFAULT_EMAIL_ASSIGNMENTS[$lowerEmail]) || $this->businessUnits()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah user diizinkan mengakses menu dan fitur Riwayat & Import Data (/sync).
     * Pak Adri (adrian.devianto@mekar-jaya.com) dan Cik Imel (imelda.daniel@mekar-jaya.com) tidak diperkenankan mengakses halaman ini.
     */
    public function canAccessSyncMenu(): bool
    {
        $lowerEmail = strtolower($this->email);
        $restrictedEmails = [
            'adrian.devianto@mekar-jaya.com',
            'imelda.daniel@mekar-jaya.com',
        ];

        if (in_array($lowerEmail, $restrictedEmails, true) || in_array($this->id, [5, 10], true)) {
            return false;
        }

        return true;
    }

    /**
     * Cek apakah user diizinkan mengedit / mengubah catatan dan status closing periode.
     * Pak Adrian (adrian.devianto@mekar-jaya.com) dan Bu Imel (imelda.daniel@mekar-jaya.com)
     * hanya dapat melihat catatan (read-only/viewer), tidak dapat merubahnya.
     */
    public function canEditPeriodNotes(): bool
    {
        $lowerEmail = strtolower($this->email);
        $viewOnlyEmails = [
            'adrian.devianto@mekar-jaya.com',
            'imelda.daniel@mekar-jaya.com',
        ];

        if (in_array($lowerEmail, $viewOnlyEmails, true)) {
            return false;
        }

        return true;
    }

    /**
     * Dapatkan daftar kode unit bisnis yang dapat diakses user.
     * Return null jika unrestricted (semua unit + konsolidasi holding).
     *
     * @return array<string>|null
     */
    public function accessibleBusinessUnitCodes(): ?array
    {
        if ($this->isUnrestrictedFinance()) {
            return null;
        }

        $assigned = $this->businessUnits()->pluck('business_unit_code')->all();
        if (! empty($assigned)) {
            return array_values(array_unique($assigned));
        }

        $lowerEmail = strtolower($this->email);
        if (isset(self::DEFAULT_EMAIL_ASSIGNMENTS[$lowerEmail])) {
            return self::DEFAULT_EMAIL_ASSIGNMENTS[$lowerEmail];
        }

        return null;
    }

    /**
     * Cek apakah user berhak mengakses unit bisnis tertentu.
     */
    public function canAccessBusinessUnit(string $code): bool
    {
        if ($this->isUnrestrictedFinance()) {
            return true;
        }

        $allowed = $this->accessibleBusinessUnitCodes();
        if ($allowed === null) {
            return true;
        }

        return in_array(strtolower($code), array_map('strtolower', $allowed), true);
    }

    /**
     * Cek apakah user berhak mengedit / menambah akun manual pada unit bisnis tertentu.
     * Hanya unit bisnis bersumber Excel ('data_source' === 'excel') yang boleh diedit.
     */
    public function canEditBusinessUnit(string $code): bool
    {
        if (! $this->canAccessBusinessUnit($code)) {
            return false;
        }

        $bu = GlobalBusinessUnit::query()->where('code', $code)->first();
        if (! $bu || $bu->data_source !== 'excel') {
            return false;
        }

        if ($this->isUnrestrictedFinance()) {
            return true;
        }

        $pivot = $this->businessUnits()->where('business_unit_code', $code)->first();
        if ($pivot) {
            return (bool) $pivot->can_edit;
        }

        return true;
    }

    /**
     * Dapatkan nama role pengguna (tersinkronisasi dinamis dengan portal Mekar Jaya & SIR-IQOS).
     */
    public function getRoleAttribute(): string
    {
        // 1. Ambil role resmi dari mekarjaya_db (Portal Superapp) jika terhubung (hanya di non-testing)
        if (! app()->environment('testing')) {
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
