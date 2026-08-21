<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function countCompletedByEmail(string $email): int
    {
        $normalizedEmail = $this->normalizeEmail($email);

        $result = $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->andWhere('LOWER(reservation.email) = :email')
            ->andWhere('reservation.status = :status')
            ->setParameter('email', $normalizedEmail)
            ->setParameter('status', ReservationStatus::COMPLETED->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function hasActiveOrUsedLoyaltyDiscountByEmail(
        string $email
    ): bool {
        $normalizedEmail = $this->normalizeEmail($email);

        $result = $this->createQueryBuilder('reservation')
            ->select('COUNT(reservation.id)')
            ->andWhere('LOWER(reservation.email) = :email')
            ->andWhere(
                'reservation.loyaltyDiscountApplied = :discountApplied'
            )
            ->andWhere('reservation.status != :cancelledStatus')
            ->setParameter('email', $normalizedEmail)
            ->setParameter('discountApplied', true)
            ->setParameter(
                'cancelledStatus',
                ReservationStatus::CANCELLED->value
            )
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result > 0;
    }

    private function normalizeEmail(string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            throw new InvalidArgumentException(
                'The customer email cannot be empty.'
            );
        }

        return $normalizedEmail;
    }
}