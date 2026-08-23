<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Reservation;
use App\Enum\PricingMode;
use App\Enum\ReservationType;
use App\Enum\VehicleType;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:demo:create-reservation',
    description: 'Crée une réservation de démonstration pour un client.',
)]
final class CreateDemoReservationCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Adresse email du client'
            )
            ->addOption(
                'completed',
                null,
                InputOption::VALUE_NONE,
                'Crée une réservation terminée'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if ($this->kernel->getEnvironment() === 'prod') {
            $io->error(
                'Cette commande est interdite en production.'
            );

            return Command::FAILURE;
        }

        $email = strtolower(
            trim((string) $input->getArgument('email'))
        );

        $isCompleted = (bool) $input->getOption('completed');

        $user = $this->userRepository->findOneBy([
            'email' => $email,
        ]);

        if ($user === null) {
            $io->error(sprintf(
                'Aucun client trouvé avec l’adresse %s.',
                $email
            ));

            return Command::FAILURE;
        }

        $scheduledAt = $isCompleted
            ? new DateTimeImmutable('-1 day 10:00')
            : new DateTimeImmutable('+1 day 10:00');

        $reservation = (new Reservation())
            ->setCustomer($user)
            ->setType(ReservationType::AIRPORT)
            ->setVehicleType(VehicleType::BERLINE)
            ->setPricingMode(PricingMode::FIXED_FARE)
            ->setFirstName('Test')
            ->setLastName('Client')
            ->setEmail($email)
            ->setPhone('+33 7 58 30 64 17')
            ->setPickupAddress('10 rue de Rivoli, 75001 Paris')
            ->setDropoffAddress(
                'Aéroport Paris-Charles de Gaulle'
            )
            ->setScheduledAt($scheduledAt)
            ->setPassengers(2)
            ->setLuggage(2)
            ->setDistanceKm('28.50')
            ->setDurationMinutes(45)
            ->setBasePrice('75.00')
            ->setDiscountPercentage(0)
            ->setDiscountAmount('0.00')
            ->setFinalPrice('75.00')
            ->setPriceIsEstimated(true)
            ->setTransportReference('AF1234')
            ->setNotes(
                'Réservation de démonstration.'
            );

        if ($isCompleted) {
            $reservation
                ->confirm()
                ->complete();
        }

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $io->success(sprintf(
            'Réservation %s créée pour %s.',
            $reservation->getReference(),
            $email
        ));

        return Command::SUCCESS;
    }
}