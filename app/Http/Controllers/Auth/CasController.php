<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class CasController extends Controller
{
    /**
     * Redirect user to CAS login page.
     */
    public function redirect()
    {
        $serviceUrl = url('/auth/cas/callback');
        $casLoginUrl = sprintf(
            'https://%s%s/login?service=%s',
            config('cas.hostname'),
            config('cas.uri'),
            urlencode($serviceUrl)
        );

        return redirect($casLoginUrl);
    }

    /**
     * Handle CAS callback after successful authentication.
     */
    public function callback(Request $request)
    {
        $ticket = $request->query('ticket');

        if (! $ticket) {
            return redirect()->route('login')->withErrors(['sso' => 'Tidak ada tiket dari SSO. Silakan coba lagi.']);
        }

        $ssoId = $this->validateTicket($ticket);

        if (! $ssoId) {
            return redirect()->route('login')->withErrors(['sso' => 'Validasi SSO gagal. Silakan coba lagi.']);
        }

        $user = $this->findOrCreateUser($ssoId);

        $request->session()->regenerate();
        Auth::login($user, remember: true);
        $request->session()->save();

        return $this->redirectAfterLogin($user);
    }

    /**
     * Validate CAS ticket and return the SSO user ID.
     */
    private function validateTicket(string $ticket): ?string
    {
        $serviceUrl  = url('/auth/cas/callback');
        $validateUrl = sprintf(
            'https://%s%s/serviceValidate?service=%s&ticket=%s',
            config('cas.hostname'),
            config('cas.uri'),
            urlencode($serviceUrl),
            urlencode($ticket)
        );

        try {
            $response = Http::withOptions(['verify' => false])->get($validateUrl);

            if (! $response->successful()) {
                return null;
            }

            if (preg_match('/<cas:user>(.+?)<\/cas:user>/s', $response->body(), $matches)) {
                return trim($matches[1]);
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('CAS ticket validation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Find existing user or create a new one based on SSO ID.
     *
     * SSO ID convention (UPI):
     *  - length > 7  → Staff / Dosen (NIP)
     *  - length <= 7 → Mahasiswa (NIM)
     */
    private function findOrCreateUser(string $ssoId): User
    {
        $user = User::where('sso', $ssoId)->first();

        if ($user) {
            return $user;
        }

        $isStaff = strlen($ssoId) > 7;

        $user = User::create([
            'sso'  => $ssoId,
            'name' => $isStaff ? $ssoId : ('s' . $ssoId),
        ]);

        $user->assignRole($isStaff ? 'admin' : 'user');

        return $user;
    }

    /**
     * Redirect user to the appropriate page after login.
     */
    private function redirectAfterLogin(User $user): mixed
    {
        if ($user->hasRole('super-admin')) {
            return redirect()->route('super-admin.idx');
        }

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.idx');
        }

        return redirect('/');
    }

    /**
     * Logout from both local session and CAS.
     */
    public function logout(Request $request): mixed
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $casLogoutUrl  = config('cas.logout_url');
        $redirectAfter = urlencode(route('login'));

        return redirect("{$casLogoutUrl}?service={$redirectAfter}");
    }
}
