<?php

namespace App\Form\Front;

use App\Entity\Club;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fiche du club, modifiable par ses responsables (ClubVoter) : nom long, nom court, description, lien.
 * Repris par le formulaire de l'admin (ClubType), qui y ajoute équipes, gymnases et responsables.
 */
class ClubFicheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom du club'])
            ->add('nomCourt', TextType::class, [
                'label' => 'Abréviation',
                'help' => 'Affichée entre parenthèses après le nom des équipes du club, ex. EROAK (AOVB).',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Facultatif. Présentation du club : histoire, entraînements, contact…',
                'attr' => ['rows' => 6],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'Facultatif. Site du club ou page de réseau social.',
                'attr' => ['placeholder' => 'https://'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
        ]);
    }
}
