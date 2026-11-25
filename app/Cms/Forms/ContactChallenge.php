<?php

namespace App\Cms\Forms;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContactChallenge
{
    private const LIFETIME = 7200;

    public static function enabled(): bool
    {
        return (bool) setting('contact_math_enabled', true);
    }

    public function issue(Request $request): array
    {
        $left = random_int(1, 9);
        $right = random_int(1, 9);
        $token = Crypt::encryptString(json_encode([
            'id' => Str::random(40),
            'session' => $request->session()->token(),
            'expires' => now()->timestamp + self::LIFETIME,
            'answer' => (string) ($left + $right),
        ], JSON_THROW_ON_ERROR));
        $request->attributes->set('has_contact_challenge', true);

        return ['question' => "What is {$left} + {$right}?", 'token' => $token];
    }

    public function submit(Request $request, Closure $save): mixed
    {
        if (! self::enabled()) {
            return $save();
        }

        $token = $request->input('_contact_challenge');
        try {
            $challenge = is_string($token) ? json_decode(Crypt::decryptString($token), true) : null;
        } catch (DecryptException) {
            $challenge = null;
        }

        if (! is_string($challenge['id'] ?? null) || ($challenge['session'] ?? null) !== $request->session()->token()
            || ($challenge['expires'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['contact_answer' => 'Please answer the new question and send your message again.']);
        }
        if ($request->input('contact_answer') !== $challenge['answer']) {
            throw ValidationException::withMessages(['contact_answer' => 'That answer was incorrect. Please answer the new question.']);
        }

        // Atomically claim the solved question before saving, including concurrent double clicks.
        $key = 'contact-receipt:'.$challenge['id'];
        if (! Cache::add($key, true, self::LIFETIME)) {
            return null;
        }

        try {
            return $save();
        } catch (\Throwable $e) {
            Cache::forget($key);
            throw $e;
        }
    }
}
