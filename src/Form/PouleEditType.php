<?php

namespace App\Form;

use App\Entity\Poule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Page « Modifier la poule » (docs/wizard-saison/decisions.md, D52) : nom et niveau seulement. La phase ne change pas,
 * les équipes se composent dans la mise en place, les journées dans leur calendrier. Niveau verrouillé quand la phase a
 * commencé (option niveau_verrouille) : un champ désactivé n'est jamais lu dans la requête.
 */
class PouleEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la poule',
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indiquez le nom de la poule.'),
                    new Assert\Length(max: 255, maxMessage: '255 caractères au plus.'),
                ],
            ])
            ->add('niveau', IntegerType::class, [
                'label' => 'Niveau (1 = le plus fort)',
                'disabled' => $options['niveau_verrouille'],
                'constraints' => [
                    new Assert\NotNull(message: 'Indiquez le niveau de la poule.'),
                    new Assert\Range(min: 1, max: 20, notInRangeMessage: 'Le niveau est un nombre de 1 à 20.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Poule::class, 'niveau_verrouille' => false]);
        $resolver->setAllowedTypes('niveau_verrouille', 'bool');
    }
}
