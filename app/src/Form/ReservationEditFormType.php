<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Reservation;
use App\Enum\VehicleType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ReservationEditFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('pickupAddress', TextType::class, [
                'label' => 'Adresse de départ',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez indiquer l’adresse de départ.',
                    ]),
                ],
            ])

            ->add('dropoffAddress', TextType::class, [
                'label' => 'Adresse d’arrivée',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez indiquer l’adresse d’arrivée.',
                    ]),
                ],
            ])

            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner une date et une heure.',
                    ]),
                ],
            ])

            ->add('vehicleType', ChoiceType::class, [
                'label' => 'Véhicule',
                'choices' => [
                    'Gamme Éco' => VehicleType::ECO,
                    'Gamme Berline' => VehicleType::BERLINE,
                    'Gamme Van' => VehicleType::VAN,
                ],
                'choice_value' => static fn (?VehicleType $vehicle): ?string =>
                    $vehicle?->value,
            ])

            ->add('passengers', IntegerType::class, [
                'label' => 'Passagers',
                'attr' => [
                    'min' => 1,
                    'max' => 7,
                ],
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 1,
                        'message' => 'Il doit y avoir au moins un passager.',
                    ]),
                    new LessThanOrEqual([
                        'value' => 7,
                        'message' => 'Le nombre de passagers ne peut pas dépasser 7.',
                    ]),
                ],
            ])

            ->add('luggage', IntegerType::class, [
                'label' => 'Bagages',
                'attr' => [
                    'min' => 0,
                    'max' => 6,
                ],
                'constraints' => [
                    new GreaterThanOrEqual([
                        'value' => 0,
                    ]),
                    new LessThanOrEqual([
                        'value' => 6,
                        'message' => 'Le nombre de bagages ne peut pas dépasser 6.',
                    ]),
                ],
            ])

            ->add('childSeatRequested', CheckboxType::class, [
                'label' => 'Siège enfant',
                'required' => false,
            ])

            ->add('pickupLongitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])

            ->add('pickupLatitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])

            ->add('dropoffLongitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])

            ->add('dropoffLatitude', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}