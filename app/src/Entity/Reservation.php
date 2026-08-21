<?php

namespace App\Entity;

use App\Enum\PricingMode;
use App\Enum\ReservationStatus;
use App\Enum\ReservationType;
use App\Enum\VehicleType;
use App\Repository\ReservationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
#[ORM\HasLifecycleCallbacks]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    private string $reference;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $customer = null;

    #[ORM\Column(enumType: ReservationType::class)]
    private ReservationType $type = ReservationType::STANDARD;

    #[ORM\Column(enumType: ReservationStatus::class)]
    private ReservationStatus $status = ReservationStatus::PENDING;

    #[ORM\Column(enumType: VehicleType::class)]
    private VehicleType $vehicleType = VehicleType::ECO;

    #[ORM\Column(enumType: PricingMode::class)]
    private PricingMode $pricingMode = PricingMode::DISTANCE_TIME;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre prénom.')]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre nom.')]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre adresse email.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Veuillez saisir votre téléphone.')]
    #[Assert\Regex(
        pattern: '/^\+?[0-9\s().-]{8,30}$/',
        message: 'Veuillez saisir un numéro de téléphone valide.'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez saisir l’adresse de départ.')]
    private ?string $pickupAddress = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez saisir l’adresse d’arrivée.')]
    private ?string $dropoffAddress = null;

    #[ORM\Column]
    private ?DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(
        min: 1,
        max: 7,
        notInRangeMessage: 'Le nombre de passagers doit être compris entre 1 et 7.'
    )]
    private int $passengers = 1;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(
        min: 0,
        max: 6,
        notInRangeMessage: 'Le nombre de bagages doit être compris entre 0 et 6.'
    )]
    private int $luggage = 0;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 8,
        scale: 2,
        nullable: true
    )]
    private ?string $distanceKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        nullable: true
    )]
    private ?string $basePrice = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $discountPercentage = 0;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        options: ['default' => '0.00']
    )]
    private string $discountAmount = '0.00';

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        nullable: true
    )]
    private ?string $finalPrice = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $priceIsEstimated = true;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transportReference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $emailMarketingConsent = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $smsMarketingConsent = false;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $loyaltyDiscountApplied = false;

    public function __construct()
    {
        $now = new DateTimeImmutable();

        $this->reference = sprintf(
            'TPS24-%s-%s',
            $now->format('Ymd'),
            strtoupper(bin2hex(random_bytes(4)))
        );

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getType(): ReservationType
    {
        return $this->type;
    }

    public function setType(ReservationType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): ReservationStatus
    {
        return $this->status;
    }

    public function getVehicleType(): VehicleType
    {
        return $this->vehicleType;
    }

    public function setVehicleType(VehicleType $vehicleType): static
    {
        $this->vehicleType = $vehicleType;

        return $this;
    }

    public function getPricingMode(): PricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(PricingMode $pricingMode): static
    {
        $this->pricingMode = $pricingMode;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getPickupAddress(): ?string
    {
        return $this->pickupAddress;
    }

    public function setPickupAddress(string $pickupAddress): static
    {
        $this->pickupAddress = trim($pickupAddress);

        return $this;
    }

    public function getDropoffAddress(): ?string
    {
        return $this->dropoffAddress;
    }

    public function setDropoffAddress(string $dropoffAddress): static
    {
        $this->dropoffAddress = trim($dropoffAddress);

        return $this;
    }

    public function getScheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getPassengers(): int
    {
        return $this->passengers;
    }

    public function setPassengers(int $passengers): static
    {
        $this->passengers = $passengers;

        return $this;
    }
    public function getLuggage(): int
    {
        return $this->luggage;
    }

    public function setLuggage(int $luggage): static
    {
        $this->luggage = $luggage;

        return $this;
    }

    public function getDistanceKm(): ?string
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(?string $distanceKm): static
    {
        $this->distanceKm = $distanceKm;

        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getDiscountPercentage(): int
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(int $discountPercentage): static
    {
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            throw new DomainException(
                'Le pourcentage de réduction doit être compris entre 0 et 100.'
            );
        }

        $this->discountPercentage = $discountPercentage;

        return $this;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getFinalPrice(): ?string
    {
        return $this->finalPrice;
    }

    public function setFinalPrice(?string $finalPrice): static
    {
        $this->finalPrice = $finalPrice;

        return $this;
    }

    public function isPriceEstimated(): bool
    {
        return $this->priceIsEstimated;
    }

    public function setPriceIsEstimated(bool $priceIsEstimated): static
    {
        $this->priceIsEstimated = $priceIsEstimated;

        return $this;
    }

    public function getTransportReference(): ?string
    {
        return $this->transportReference;
    }

    public function setTransportReference(?string $transportReference): static
    {
        $this->transportReference = $transportReference !== null
            ? trim($transportReference)
            : null;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes !== null ? trim($notes) : null;

        return $this;
    }

    public function hasEmailMarketingConsent(): bool
    {
        return $this->emailMarketingConsent;
    }

    public function setEmailMarketingConsent(bool $consent): static
    {
        $this->emailMarketingConsent = $consent;

        return $this;
    }

    public function hasSmsMarketingConsent(): bool
    {
        return $this->smsMarketingConsent;
    }

    public function setSmsMarketingConsent(bool $consent): static
    {
        $this->smsMarketingConsent = $consent;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function confirm(): static
    {
        $this->transitionTo(ReservationStatus::CONFIRMED);
        $this->confirmedAt = new DateTimeImmutable();

        return $this;
    }
    public function complete(): static
    {
        $this->transitionTo(ReservationStatus::COMPLETED);
        $this->completedAt = new DateTimeImmutable();

        return $this;
    }

    public function cancel(?string $reason = null): static
    {
        $this->transitionTo(ReservationStatus::CANCELLED);
        $this->cancelledAt = new DateTimeImmutable();
        $this->cancellationReason = $reason !== null
            ? trim($reason)
            : null;

        return $this;
    }

    private function transitionTo(ReservationStatus $nextStatus): void
    {
        if (!$this->status->canTransitionTo($nextStatus)) {
            throw new DomainException(sprintf(
                'Transition impossible du statut "%s" vers "%s".',
                $this->status->value,
                $nextStatus->value
            ));
        }

        $this->status = $nextStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isLoyaltyDiscountApplied(): bool
    {
        return $this->loyaltyDiscountApplied;
    }

    public function setLoyaltyDiscountApplied(bool $loyaltyDiscountApplied): static
    {
        $this->loyaltyDiscountApplied = $loyaltyDiscountApplied;

        return $this;
    }
}
