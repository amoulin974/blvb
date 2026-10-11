<?php

namespace App\Form\Front;

use App\Entity\Club;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * Fiche du club, modifiable par ses responsables (ClubVoter) : nom long, nom court, description, lien, image.
 * L'image est enregistrée par ClubImageService::appliquerFormulaire (champs non liés à l'entité).
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
            ->add('imageFichier', FileType::class, [
                'label' => 'Image (logo)',
                'mapped' => false,
                'required' => false,
                'help' => 'Facultatif. JPEG, PNG, WebP ou GIF, 2 Mo au plus. Une image carrée s\'affiche le mieux.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/gif'],
                'constraints' => [new Image(
                    maxSize: '2M',
                    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    mimeTypesMessage: 'Choisissez une image JPEG, PNG, WebP ou GIF.',
                    maxSizeMessage: 'L\'image est trop lourde ({{ size }} {{ suffix }}) : 2 Mo au plus.',
                )],
            ])
            ->add('supprimerImage', CheckboxType::class, [
                'label' => 'Retirer l\'image actuelle',
                'mapped' => false,
                'required' => false,
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
