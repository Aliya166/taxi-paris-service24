<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final class MarketingUnsubscribeTokenService
{
    public function __construct(
        private readonly string $appSecret
    ) {
    }

    public function generate(User $user): string
    {
        $email = (string) $user->getEmail();

        return hash_hmac(
            'sha256',
            $email,
            $this->appSecret
        );
    }

    public function isValid(
        User $user,
        string $token
    ): bool {
        return hash_equals(
            $this->generate($user),
            $token
        );
    }
}