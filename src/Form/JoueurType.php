<?php

namespace App\Form;

use App\Entity\Joueur;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JoueurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentJoueurId = $options['current_joueur_id'];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'Compte utilisateur lié',
                'placeholder' => 'Aucun compte lié',
                'required' => false,
                'attr' => [
                    'data-controller' => 'tom-select',
                ],
                // Un compte ne peut être lié qu'à une seule fiche joueur (cf. UniqueEntity
                // sur Joueur::user) : on exclut donc les comptes déjà liés à une autre
                // fiche, pour éviter l'erreur de contrainte unique en base.
                'query_builder' => function (UserRepository $userRepository) use ($currentJoueurId) {
                    $qb = $userRepository->createQueryBuilder('u')
                        ->leftJoin(Joueur::class, 'j', 'WITH', 'j.user = u')
                        ->orderBy('u.email', 'ASC');

                    if ($currentJoueurId !== null) {
                        $qb->andWhere('j.id IS NULL OR j.id = :currentJoueurId')
                            ->setParameter('currentJoueurId', $currentJoueurId);
                    } else {
                        $qb->andWhere('j.id IS NULL');
                    }

                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Joueur::class,
            'current_joueur_id' => null,
        ]);
    }
}
