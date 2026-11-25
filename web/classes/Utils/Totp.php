<?php

    namespace Utils;

    /**
     * Time-based one-time passwords (RFC 6238) as authenticator apps make them:
     * HMAC-SHA1, 6 digits, a new code every 30 seconds, base32 secrets (RFC 4648).
     * Also the single-use recovery codes that stand in for the app when it is lost.
     */
    class Totp
    {
        const PERIOD = 30;
        const DIGITS = 6;

        // Codes of the previous and next 30 seconds are accepted too, for clocks a
        // little off and codes typed just as they change
        const WINDOW = 1;

        // 160 bits, the HMAC-SHA1 key size RFC 4226 recommends
        const SECRET_BYTES = 20;

        // 12 base32 characters (60 bits), shown as XXXX-XXXX-XXXX
        const RECOVERY_LENGTH = 12;

        const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        public static function generateSecret() : string
        {
            return self::base32Encode(random_bytes(self::SECRET_BYTES));
        }

        public static function base32Encode(string $bytes) : string
        {
            $bits = '';
            foreach (str_split($bytes) as $byte) {
                $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
            }

            $out = '';
            foreach (str_split($bits, 5) as $chunk) {
                $out .= self::BASE32[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
            }

            return $out;
        }

        // Null when $text is not base32 (spaces and padding are ignored, any case)
        public static function base32Decode(string $text) : ?string
        {
            $text = strtoupper(str_replace([' ', '='], '', $text));
            if ($text === '' || strspn($text, self::BASE32) !== strlen($text)) {
                return null;
            }

            $bits = '';
            foreach (str_split($text) as $char) {
                $bits .= str_pad(decbin(strpos(self::BASE32, $char)), 5, '0', STR_PAD_LEFT);
            }

            $out = '';
            foreach (str_split($bits, 8) as $chunk) {
                if (strlen($chunk) === 8) {
                    $out .= chr(bindec($chunk));
                }
            }

            return $out;
        }

        public static function step(int $now) : int
        {
            return intdiv($now, self::PERIOD);
        }

        // The code of time step $step for $secret (base32)
        public static function code(string $secret, int $step) : string
        {
            $key = self::base32Decode($secret) ?? '';
            $hash = hash_hmac('sha1', pack('J', $step), $key, true);
            $offset = ord($hash[19]) & 0x0f;
            $value = ((ord($hash[$offset]) & 0x7f) << 24)
                | (ord($hash[$offset + 1]) << 16)
                | (ord($hash[$offset + 2]) << 8)
                | ord($hash[$offset + 3]);

            return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
        }

        /**
         * The time step whose code is $code, among those around $now that come after
         * $lastStep (a code already used can't be used again), or null. Every step of
         * the window is compared, so the time taken tells nothing about which matched.
         */
        public static function matchingStep(string $secret, string $code, int $now, int $lastStep) : ?int
        {
            if (strlen($code) !== self::DIGITS || !ctype_digit($code)) {
                return null;
            }

            $found = null;
            $current = self::step($now);
            for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
                if (hash_equals(self::code($secret, $step), $code) && $step > $lastStep && $found === null) {
                    $found = $step;
                }
            }

            return $found;
        }

        // otpauth:// address that authenticator apps read from the QR code
        public static function uri(string $issuer, string $account, string $secret) : string
        {
            return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
                . '?secret=' . $secret
                . '&issuer=' . rawurlencode($issuer)
                . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
        }

        public static function generateRecoveryCode() : string
        {
            $code = '';
            for ($i = 0; $i < self::RECOVERY_LENGTH; $i++) {
                $code .= self::BASE32[random_int(0, 31)];
            }

            return implode('-', str_split($code, 4));
        }

        // A typed recovery code without its dashes and spaces, upper case, or null
        // when it can't be one
        public static function normalizeRecoveryCode(string $input) : ?string
        {
            $code = strtoupper(str_replace(['-', ' '], '', $input));

            return (strlen($code) === self::RECOVERY_LENGTH && strspn($code, self::BASE32) === self::RECOVERY_LENGTH) ? $code : null;
        }

        // Stored instead of the code. 60 random bits: a fast hash is enough.
        public static function hashRecoveryCode(string $normalized) : string
        {
            return hash('sha256', $normalized);
        }
    }
