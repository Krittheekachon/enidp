<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KkuSsoController extends Controller
{
    private const ATTEMPT_TTL_SECONDS = 900;

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->fail('ระบบ KKU SSO ยังตั้งค่าไม่ครบ กรุณาเข้าสู่ระบบด้วยวิธีอื่นหรือติดต่อผู้ดูแลระบบ');
        }

        $request->session()->put('kku_sso_attempted_at', now()->timestamp);

        return redirect()->away(config('services.kku_sso.login_url').'?'.http_build_query([
            'app' => config('services.kku_sso.app_id'),
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return $this->fail('ระบบ KKU SSO ยังตั้งค่าไม่ครบ กรุณาเข้าสู่ระบบด้วยวิธีอื่นหรือติดต่อผู้ดูแลระบบ');
        }

        $attemptedAt = (int) $request->session()->pull('kku_sso_attempted_at', 0);
        if ($attemptedAt === 0 || now()->timestamp - $attemptedAt > self::ATTEMPT_TTL_SECONDS) {
            return $this->fail('คำขอเข้าสู่ระบบ KKU SSO หมดอายุ กรุณาลองเข้าสู่ระบบอีกครั้ง');
        }

        $code = trim((string) $request->query('code'));
        if ($code === '') {
            return $this->fail('KKU SSO ไม่ได้ส่งรหัสยืนยันกลับมา กรุณาลองเข้าสู่ระบบอีกครั้ง');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(10)
                ->post(config('services.kku_sso.token_url'), [
                    'code' => $code,
                    'redirectUrl' => config('services.kku_sso.redirect_url'),
                    'clientId' => config('services.kku_sso.client_id'),
                    'clientSecret' => config('services.kku_sso.client_secret'),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('KKU SSO token endpoint could not be reached.', [
                'exception' => $exception::class,
            ]);

            return $this->fail('ไม่สามารถเชื่อมต่อ KKU SSO ได้ในขณะนี้ กรุณาลองใหม่อีกครั้ง');
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            Log::warning('KKU SSO rejected a token exchange.', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return $this->fail('KKU SSO ไม่สามารถยืนยันตัวตนได้ กรุณาลองเข้าสู่ระบบอีกครั้ง');
        }

        $employeeId = trim((string) $response->json('employeeId'));
        $email = mb_strtolower(trim((string) $response->json('email')));

        $user = $employeeId !== ''
            ? User::query()->where('sso', $employeeId)->first()
            : null;

        if (! $user && $email !== '') {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        if (! $user) {
            return $this->fail('ไม่พบบัญชีผู้ใช้นี้ในระบบ EN-IDP กรุณาติดต่อผู้ดูแลระบบ');
        }

        if (! $user->is_active) {
            return $this->fail('บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('kku_sso_authenticated', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function isConfigured(): bool
    {
        return (bool) config('services.kku_sso.enabled')
            && filled(config('services.kku_sso.app_id'))
            && filled(config('services.kku_sso.client_id'))
            && filled(config('services.kku_sso.client_secret'))
            && filled(config('services.kku_sso.redirect_url'));
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect('/')->with('sso_error', $message);
    }
}
