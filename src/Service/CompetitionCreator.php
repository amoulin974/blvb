<?php
// src/Service/CompetitionCreator.php

namespace App\Service;

use App\Entity\Saison;
use App\Entity\Phase;
use App\Entity\Poule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use DateTimeImmutable;
class CompetitionCreator
{
    public function __construct(
        private ParameterBagInterface $params,
        private EntityManagerInterface $em
    ) {}

    /**
     * Crée automatiquement les phases et poules pour une saison
     * à partir de la configuration YAML (config/app/saison.yaml).
     */
    public function creerPhasesEtPoules(Saison $saison): void
    {
        // On récupère la configuration
        $config = $this->params->get('saison');

        if (!isset($config['phases']) || !is_array($config['phases'])) {
            throw new \RuntimeException("La configuration des phases est absente ou invalide.");
        }

        foreach ($config['phases'] as $phaseData) {
            $phase = new Phase();
            $phase->setNom($phaseData['nom'] ?? 'Phase sans nom');



            $phase->setDateDebut(
                $this->parseYamlDate($phaseData['date_debut'], $saison->getDateDebut())
            );
            $phase->setDateFin(
                $this->parseYamlDate($phaseData['date_fin'], $saison->getDateDebut())
            );
            $phase->setSaison($saison);
            $this->em->persist($phase);

            // Vérifie s’il y a des poules configurées pour cette phase
            if (isset($phaseData['poules']) && is_array($phaseData['poules'])) {
                foreach ($phaseData['poules'] as $pouleData) {
                    $poule = new Poule();
                    $poule->setNom($pouleData['nom'] ?? 'Poule sans nom');
                    $poule->setPhase($phase);
                    $this->em->persist($poule);
                }
            }
        }

        // On sauvegarde toutes les entités créées

            $this->em->flush();

    }

    private function parseYamlDate(string $yamlDate, DateTimeImmutable $saisonDebut): DateTimeImmutable
    {
        $year = $saisonDebut->format('Y'); // année de début de la saison

        // Remplace "n+1" ou "n" dans le YAML
        $yearReplaced = preg_replace_callback('/n(\+1)?/', function($matches) use ($year) {
            if (isset($matches[1]) && $matches[1] === '+1') {
                return $year + 1;
            }
            return $year;
        }, $yamlDate);

        // Création d'un DateTimeImmutable à partir de la chaîne
        // On suppose que le format est toujours "d/m/Y"
        $date = DateTimeImmutable::createFromFormat('d/m/Y', $yearReplaced);

        if (!$date) {
            throw new \Exception("Impossible de parser la date YAML : $yamlDate");
        }

        return $date;
    }
}
