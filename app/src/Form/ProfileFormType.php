<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ProfileFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'autocomplete' => 'given-name',
                    'placeholder' => 'Votre prénom',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre prénom.'
                    ),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'autocomplete' => 'family-name',
                    'placeholder' => 'Votre nom',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre nom.'
                    ),
                    new Length(
                        min: 2,
                        max: 100,
                        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'attr' => [
                    'autocomplete' => 'tel',
                    'placeholder' => '+33 6 12 34 56 78',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre numéro de téléphone.'
                    ),
                    new Regex(
                        pattern: '/^\+?[0-9\s().-]{8,20}$/',
                        message: 'Veuillez saisir un numéro de téléphone valide.'
                    ),
                ],
            ])
            ->add('emailMarketingConsent', CheckboxType::class, [
                'label' => 'Je souhaite recevoir les offres et actualités par email.',
                'required' => false,
            ])
            ->add('smsMarketingConsent', CheckboxType::class, [
                'label' => 'Je souhaite recevoir les offres et actualités par SMS.',
                'required' => false,
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver
    ): void {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}