<?php

namespace App\Form;

use App\Entity\Equipe;
use App\Entity\Lieu;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom',
            ])
            ->add('capitaine', EntityType::class, [ // nouveau champ
                'class' => User::class,
                'choice_label' => 'email', // ou 'username' selon ton User
                'placeholder' => 'Choisissez un capitaine',
                'required' => false,
                'attr' => [
                    'data-controller' => 'tom-select',
                    'data-tom-select-multiple-value' => 'false',
                    'data-tom-select-placeholder-value' => 'Choisissez un capitaine...'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipe::class,
        ]);
    }
}
