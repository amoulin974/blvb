<?php

namespace App\Form;

use App\Entity\Indisponibilite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IndisponibiliteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Motif',
                // Style du Label
                'label_attr' => ['class' => 'label-text font-bold'],
                // Style de l'Input
                'attr' => [
                    'class' => 'input input-bordered w-full input-sm', // input-sm pour un rendu plus compact
                    'placeholder' => 'Ex: Vacances de Noël'
                ],
                // Style du conteneur du champ (Row)
                'row_attr' => ['class' => 'form-control w-full mb-2'],
            ])

            // --- Dates ---
            // Astuce : On ne peut pas facilement faire une grid ici sans thème form personnalisé.
            // Mais on applique le style aux inputs, et le CSS parent fera le reste.

            ->add('dateDebut', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'label_attr' => ['class' => 'label-text'],
                'attr' => ['class' => 'input input-bordered w-full input-sm'],
                'row_attr' => ['class' => 'form-control w-full'],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'input'  => 'datetime_immutable',
                'label_attr' => ['class' => 'label-text'],
                'attr' => ['class' => 'input input-bordered w-full input-sm'],
                'row_attr' => ['class' => 'form-control w-full'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Indisponibilite::class,
        ]);
    }
}
