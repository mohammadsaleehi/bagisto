<?php

namespace Webkul\Torob\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TorobAuthenticate
{
    private const TOKEN_VERSION = '1';

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (
            $request->header('X-Torob-Token-Version')
            !== self::TOKEN_VERSION
        ) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        $token = $request->header('X-Torob-Token');

        if (! $token) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        try {
            $publicKey = trim(
                (string) config('torob.api_public_key')
            );

            if ($publicKey === '') {
                throw new \RuntimeException(
                    'Torob API public key is not configured.'
                );
            }

            $decodedKey = base64_decode(
                $publicKey,
                true
            );

            if (
                $decodedKey === false
                || strlen($decodedKey) < 32
            ) {
                throw new \RuntimeException(
                    'Invalid Torob API public key.'
                );
            }

            $publicKeySeed = base64_encode(
                substr($decodedKey, -32)
            );

            $decoded = JWT::decode(
                $token,
                new Key($publicKeySeed, 'EdDSA')
            );

            $allowedAudiences = [
                'www.zephyras.ir',
                'zephyras.ir',
            ];

            if (
                ! isset($decoded->aud)
                || ! is_string($decoded->aud)
                || ! in_array(
                    $decoded->aud,
                    $allowedAudiences,
                    true
                )
            ) {
                return response()->json([
                    'error' => 'Unauthorized.',
                ], 401);
            }
        } catch (Throwable) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}