<?php

namespace App\Services\Auth;

use App\Exceptions\JwtConfigurationException;
use App\Exceptions\JwtException;
use JsonException;

class JwtService
{
    private const MAX_TOKEN_LENGTH = 8192;

    private string $secret;

    private string $issuer;

    private string $audience;

    private int $accessTtl;

    private int $leeway;

    public function __construct()
    {
        $secret = (string) config('jwt.secret');
        $minimum = (int) config('jwt.minimum_secret_length', 32);

        if (strlen($secret) < $minimum) {
            throw new JwtConfigurationException(
                'JWT_SECRET ausente ou curto demais. Configure uma chave aleatória com pelo menos '.$minimum.' caracteres.'
            );
        }

        $this->secret = $secret;
        $this->issuer = (string) config('jwt.issuer');
        $this->audience = (string) config('jwt.audience');
        $this->accessTtl = (int) config('jwt.access_ttl', 900);
        $this->leeway = (int) config('jwt.leeway', 30);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function encode(array $claims, ?int $ttl = null, ?int $now = null): string
    {
        $now ??= time();
        $ttl ??= $this->accessTtl;

        $payload = array_merge([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
            'typ' => 'access',
        ], $claims);

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [
            $this->base64UrlEncode($this->jsonEncode($header)),
            $this->base64UrlEncode($this->jsonEncode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token, ?int $now = null): array
    {
        $now ??= time();

        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            throw new JwtException('Token inválido.', 'malformed');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new JwtException('Token malformado.', 'malformed');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        try {
            $header = json_decode($this->base64UrlDecode($encodedHeader), true, 16, JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new JwtException('Token malformado.', 'malformed');
        }

        if (! is_array($header) || ! is_array($payload)) {
            throw new JwtException('Token malformado.', 'malformed');
        }

        $algorithm = $header['alg'] ?? null;

        if (! is_string($algorithm) || ! hash_equals('HS256', $algorithm)) {
            throw new JwtException('Algoritmo de assinatura não permitido.', 'invalid_algorithm');
        }

        if (($header['typ'] ?? 'JWT') !== 'JWT') {
            throw new JwtException('Tipo de token inválido.', 'invalid_header');
        }

        $providedSignature = $this->base64UrlDecode($encodedSignature, false);

        if ($providedSignature === null) {
            throw new JwtException('Assinatura inválida.', 'invalid_signature');
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $encodedHeader.'.'.$encodedPayload,
            $this->secret,
            true
        );

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw new JwtException('Assinatura inválida.', 'invalid_signature');
        }

        $this->validateNumericClaim($payload, 'exp', true);
        $this->validateNumericClaim($payload, 'iat', false);
        $this->validateNumericClaim($payload, 'nbf', false);

        if ((int) $payload['exp'] <= $now - $this->leeway) {
            throw new JwtException('Token expirado.', 'expired');
        }

        if ((int) $payload['iat'] > $now + $this->leeway) {
            throw new JwtException('Token emitido no futuro.', 'invalid_iat');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now + $this->leeway) {
            throw new JwtException('Token ainda não é válido.', 'not_yet_valid');
        }

        if (isset($payload['iss']) && ! hash_equals($this->issuer, (string) $payload['iss'])) {
            throw new JwtException('Emissor inválido.', 'invalid_issuer');
        }

        if (isset($payload['aud'])) {
            $audiences = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];

            if (! in_array($this->audience, array_map('strval', $audiences), true)) {
                throw new JwtException('Destinatário inválido.', 'invalid_audience');
            }
        }

        $subject = $payload['sub'] ?? null;

        if (! is_int($subject) && ! (is_string($subject) && ctype_digit($subject))) {
            throw new JwtException('Identificador de usuário inválido.', 'invalid_subject');
        }

        if (! isset($payload['jti']) || ! is_string($payload['jti']) || strlen($payload['jti']) < 16) {
            throw new JwtException('Identificador de token inválido.', 'invalid_jti');
        }

        return $payload;
    }

    private function validateNumericClaim(array $payload, string $claim, bool $required): void
    {
        if (! array_key_exists($claim, $payload)) {
            if ($required) {
                throw new JwtException('Token sem claim obrigatória.', 'missing_claim');
            }

            return;
        }

        if (! is_int($payload[$claim]) && ! (is_string($payload[$claim]) && ctype_digit($payload[$claim]))) {
            throw new JwtException('Claim temporal inválida.', 'invalid_claim');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function jsonEncode(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new JwtException('Falha ao codificar token.', 'encoding_error');
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value, bool $required = true): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            if ($required) {
                throw new JwtException('Codificação inválida.', 'malformed');
            }

            return null;
        }

        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            if ($required) {
                throw new JwtException('Codificação inválida.', 'malformed');
            }

            return null;
        }

        return $decoded;
    }
}
