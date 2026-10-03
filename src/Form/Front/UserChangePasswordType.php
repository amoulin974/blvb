<?php
namespace App\Form\Front;

use App\Validator\PasswordPolicy;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'label' => 'Mot de passe actuel',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre mot de passe actuel']),
                    new UserPassword(['message' => 'Le mot de passe actuel est incorrect.']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'first_options'  => [
                    'label' => 'Nouveau mot de passe',
                    'help' => PasswordPolicy::HELP_TEXT,
                ],
                'second_options' => ['label' => 'Confirmer le nouveau mot de passe'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez entrer un mot de passe']),
                    ...PasswordPolicy::constraints(),
                ],
            ]);
    }
}
