<?php

namespace App\Form;

use App\Entity\ParametresSite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Adresse de contact du site (D62) ; la vider la retire du pied de page. */
class ParametresContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('adresseContact', EmailType::class, [
            'label' => 'Adresse e-mail',
            'required' => false,
            'help' => 'Facultatif. Cette adresse est publique : elle s\'affiche en bas des pages du site. Les clubs s\'en servent aussi pour demander une fermeture de gymnase quand des matchs sont programmés. Si vous la laissez vide, ces demandes arrivent aux administrateurs.',
            'attr' => ['placeholder' => 'contact@exemple.fr', 'autocomplete' => 'off'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ParametresSite::class]);
    }
}
