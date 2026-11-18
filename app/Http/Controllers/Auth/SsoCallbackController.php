<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SsoCallbackController extends Controller
{
    /**
     * Tangani autentikasi SSO dari Mekar Jaya Super App.
     */
    public function handle(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        $signature = $request->query('signature');

        if (! $token || ! $signature) {
            return redirect()->route('login')->with('error', 'Parameter SSO tidak lengkap.');
        }

        $secret = config('services.sso.secret', env('SSO_SECRET', 'mekarjaya_sso_secret'));
        $expectedSignature = hash_hmac('sha256', (string) $token, (string) $secret);

        if (! hash_equals($expectedSignature, (string) $signature)) {
            // Coba fallback secret
            $fallbackSecret = 'mekarjaya_superapp_spbu_sso_secret_key_2026';
            $globalSecret = 'mekarjaya_superapp_global_finance_secret_2026';
            if (! hash_equals(hash_hmac('sha256', (string) $token, $fallbackSecret), (string) $signature) &&
                ! hash_equals(hash_hmac('sha256', (string) $token, $globalSecret), (string) $signature)) {
                return redirect()->route('login')->with('error', 'Tanda tangan SSO tidak valid.');
            }
        }

        try {
            $payload = json_decode(base64_decode($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('error', 'Format token SSO rusak.');
        }

        // Cek kedaluwarsa token (toleransi 5 menit)
        if (empty($payload['expires_at']) || $payload['expires_at'] < (now()->timestamp - 300)) {
            return redirect()->route('login')->with('error', 'Sesi token SSO telah kedaluwarsa.');
        }

        // Cari atau buat pengguna berdasarkan email SSO
        $email = $payload['email'] ?? 'superadmin@mekarjaya.com';
        $name = $payload['name'] ?? 'User Mekar Jaya';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(bin2hex(random_bytes(16))),
            ]
        );

        Auth::login($user, true);

        session([
            'sso_user' => $payload,
            'user_role' => $payload['role'] ?? 'super_admin',
        ]);

        return redirect()->route('finance.dashboard')->with('success', "Selamat datang, {$user->name}!");
    }

    /**
     * Halaman Login Manual / Direct Access.
     */
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('finance.dashboard');
        }

        // Jika bukan bypass manual (?local=1), arahkan ke form login Mekar Jaya Portal persis SIR-IQOS
        if (! $request->boolean('local')) {
            $portalLoginUrl = rtrim(config('services.mkj_superapp.url', env('SUPERAPP_URL', 'http://localhost:8000')), '/').'/login';

            return redirect()->away($portalLoginUrl);
        }

        return view('auth.login');
    }

    /**
     * Proses Login Manual.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('finance.dashboard'));
        }

        // Fallback bypass jika menggunakan akun default admin
        if ($credentials['email'] === 'admin@mekarjaya.com' && $credentials['password'] === 'password') {
            $user = User::firstOrCreate(
                ['email' => 'admin@mekarjaya.com'],
                ['name' => 'Administrator Holding', 'password' => Hash::make('password')]
            );
            Auth::login($user, true);

            return redirect()->route('finance.dashboard');
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    /**
     * Keluar dari sesi aplikasi dan arahkan kembali ke form login Mekar Jaya Portal (Super App) persis SIR-IQOS.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $portalLogoutUrl = rtrim(config('services.mkj_superapp.url', env('SUPERAPP_URL', 'http://localhost:8000')), '/').'/logout';

        return redirect()->away($portalLogoutUrl);
    }

    /**
     * Arahkan user kembali ke Super App Portal dengan token SSO aman.
     */
    public function redirectToPortal(Request $request): RedirectResponse
    {
        $portalUrl = rtrim(config('services.mkj_superapp.url', env('SUPERAPP_URL', 'http://localhost:8000')), '/');
        $user = $request->user();

        if (! $user) {
            return redirect()->away("{$portalUrl}/login");
        }

        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role ?? 'super_admin',
            'target' => 'portal',
            'expires_at' => now()->addMinutes(2)->timestamp,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $encodedToken = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $secret = config('services.mkj_superapp.secret', env('SSO_SECRET', 'mekarjaya_sso_secret'));
        $signature = hash_hmac('sha256', $encodedToken, $secret);

        return redirect()->away("{$portalUrl}/sso/callback?token={$encodedToken}&signature={$signature}&target=portal");
    }
}
