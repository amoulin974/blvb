<?php

namespace App\Form\Wizard;

use App\Entity\Phase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Journée des finales d'un tableau à élimination (D45, D46), dans l'étape Phases du wizard. Les données sont un tableau
 * (actif, tours, lieu, date, heure, terrains, durée) que PhaseWizardType range dans les options de la phase.
 */
class JourneeFinalesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('actif', CheckboxType::class, ['label' => 'Organiser une journée des finales', 'required' => false])
            ->add('tours', ChoiceType::class, [
                'label' => 'Tours joués ce jour-là',
                'expanded' => true,
                'choices' => [
                    'Les finales seulement' => Phase::TOURS_FINALES,
                    'Les demi-finales et les finales' => Phase::TOURS_DEMI_FINALES,
                ],
            ])
            // Date d'abord, puis le gymnase libre ce jour-là (ergonome) ; contrôles dans le Callback, seulement si la case est cochée
            ->add('date', DateType::class, ['label' => 'Date', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('lieu', ChoiceType::class, ['label' => 'Gymnase', 'choices' => array_keys($options['gymnases']), 'choice_label' => fn (int $id) => $options['gymnases'][$id], 'required' => false, 'placeholder' => 'Choisissez un gymnase'])
            ->add('heure', TimeType::class, ['label' => 'Heure du premier match', 'widget' => 'single_text', 'input' => 'string', 'input_format' => 'H:i', 'with_seconds' => false, 'required' => false])
            ->add('terrains', IntegerType::class, ['label' => 'Nombre de terrains', 'required' => false, 'attr' => ['min' => 1, 'max' => 12]])
            ->add('duree', IntegerType::class, ['label' => 'Durée d\'un match (minutes)', 'required' => false, 'attr' => ['min' => 30, 'max' => 240, 'step' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Gymnases proposés (identifiant => nom), fournis par le contrôleur : les types de formulaire ne sont pas des
        // services dans ce projet ; en déclarer un priverait les autres du jeton CSRF commun « submit » (config/packages/csrf.yaml)
        $resolver->setRequired('gymnases');
        $resolver->setAllowedTypes('gymnases', 'array');
        $resolver->setDefaults([
            'label' => false,
            // Gymnase et date exigés seulement quand la journée des finales est choisie
            'constraints' => [new Assert\Callback(function (?array $valeurs, ExecutionContextInterface $contexte): void {
                if (!($valeurs['actif'] ?? false)) {
                    return;
                }
                if (($valeurs['lieu'] ?? null) === null) {
                    $contexte->buildViolation('Choisissez le gymnase de la journée des finales.')->atPath('[lieu]')->addViolation();
                }
                if (($valeurs['date'] ?? null) === null) {
                    $contexte->buildViolation('Indiquez la date de la journée des finales.')->atPath('[date]')->addViolation();
                }
                if (($valeurs['heure'] ?? null) === null) {
                    $contexte->buildViolation('Indiquez l\'heure du premier match.')->atPath('[heure]')->addViolation();
                }
                $terrains = $valeurs['terrains'] ?? null;
                if ($terrains === null || $terrains < 1 || $terrains > 12) {
                    $contexte->buildViolation('Entre 1 et 12 terrains.')->atPath('[terrains]')->addViolation();
                }
                $duree = $valeurs['duree'] ?? null;
                if ($duree === null || $duree < 30 || $duree > 240) {
                    $contexte->buildViolation('Entre 30 et 240 minutes.')->atPath('[duree]')->addViolation();
                }
            })],
        ]);
    }

    /**
     * Valeurs du formulaire pour une phase : celles de l'option, ou des valeurs proposées (19 h, 2 terrains, 90 minutes).
     *
     * @return array<string, mixed>
     */
    public static function depuisPhase(Phase $phase): array
    {
        $option = $phase->getJourneeFinales();

        return [
            'actif' => $option !== null,
            'tours' => $option['tours'] ?? Phase::TOURS_FINALES,
            'lieu' => $option['lieu'] ?? null,
            'date' => $option['date'] ?? null,
            'heure' => $option['heure'] ?? '19:00',
            'terrains' => $option['terrains'] ?? 2,
            'duree' => $option['duree'] ?? $phase->dureeMatchParDefaut(),
        ];
    }

    /** Range les valeurs du formulaire dans les options de la phase (option retirée si la case n'est pas cochée). */
    public static function versPhase(Phase $phase, ?array $valeurs): void
    {
        $options = $phase->getOptions();
        unset($options['journee_finales']);
        if ($valeurs !== null && ($valeurs['actif'] ?? false)) {
            $options['journee_finales'] = [
                'tours' => $valeurs['tours'] === Phase::TOURS_DEMI_FINALES ? Phase::TOURS_DEMI_FINALES : Phase::TOURS_FINALES,
                'lieu' => $valeurs['lieu'] !== null ? (int) $valeurs['lieu'] : null,
                'date' => $valeurs['date']?->format('Y-m-d'),
                'heure' => (string) $valeurs['heure'],
                'terrains' => (int) $valeurs['terrains'],
                'duree' => (int) $valeurs['duree'],
            ];
        }
        $phase->setOptions($options);
    }
}
