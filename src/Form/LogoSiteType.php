<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

/** Logo du site (D62) : PNG ou WebP, jamais de SVG (il peut contenir du script). Enregistré par LogoSite. */
class LogoSiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fichier', FileType::class, [
            'label' => 'Nouveau logo',
            'help' => 'Format PNG ou WebP, 1 Mo et 2 000 pixels de côté au maximum. Le logo s\'affiche sur un fond bleu marine : préférez un fond transparent et des couleurs claires.',
            'attr' => ['accept' => 'image/png,image/webp'],
            'constraints' => [
                new NotNull(message: 'Choisissez un fichier.'),
                new Image(
                    maxSize: '1M',
                    mimeTypes: ['image/png', 'image/webp'],
                    mimeTypesMessage: 'Choisissez une image PNG ou WebP.',
                    maxSizeMessage: 'L\'image est trop lourde : 1 Mo au maximum.',
                    maxWidth: 2000,
                    maxHeight: 2000,
                    maxWidthMessage: 'L\'image est trop large ({{ width }} pixels) : 2 000 pixels au maximum.',
                    maxHeightMessage: 'L\'image est trop haute ({{ height }} pixels) : 2 000 pixels au maximum.',
                ),
            ],
        ]);
    }
}
