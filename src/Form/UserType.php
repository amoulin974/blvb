<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false; // option pour différencier edit/new
        $builder
            ->add('email')
            ->add('prenom')
            ->add('nom')
            ->add('telephone')
           ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => false, // dropdown
                'multiple' => true,
                'label' => 'Rôles'
                ])
            ->add('plainPassword', PasswordType::class, [
                            'mapped' => false,
                            'required' => !$isEdit, // obligatoire pour new, optionnel pour edit
                            'attr' => ['autocomplete' => $isEdit ? 'new-password' : 'password'],
                            'label' => $isEdit 
                                ? 'Mot de passe (laisser vide pour ne pas changer)' 
                                : 'Mot de passe',
                        ])
            ->add('isVerified')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false, // option par défaut
        ]);
    }
}
