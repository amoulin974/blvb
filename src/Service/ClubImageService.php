<?php

namespace App\Service;

use App\Entity\Club;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Image (logo) d'un club : fichier enregistré dans le dossier app.dossier_images_clubs (public/uploads/clubs),
 * nom du fichier gardé dans Club::image. Remplacer ou retirer l'image supprime l'ancien fichier.
 */
final class ClubImageService
{
    public function __construct(
        #[Autowire('%app.dossier_images_clubs%')]
        private readonly string $dossier,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** Enregistre le fichier envoyé comme nouvelle image du club. Le type a été vérifié par le formulaire. */
    public function remplacer(Club $club, UploadedFile $fichier): void
    {
        $nom = sprintf('%s-%s.%s',
            strtolower($this->slugger->slug((string) $club->getNomCourt())),
            bin2hex(random_bytes(4)),
            $fichier->guessExtension() ?? 'img'
        );
        $this->filesystem->mkdir($this->dossier);
        $fichier->move($this->dossier, $nom);

        $this->supprimerFichier($club);
        $club->setImage($nom);
    }

    /** Après validation d'un formulaire de fiche (ClubFicheType) : nouvelle image envoyée, ou image retirée. */
    public function appliquerFormulaire(FormInterface $form, Club $club): void
    {
        $fichier = $form->get('imageFichier')->getData();
        if ($fichier instanceof UploadedFile) {
            $this->remplacer($club, $fichier);
        } elseif ($form->get('supprimerImage')->getData() === true) {
            $this->retirer($club);
        }
    }

    public function retirer(Club $club): void
    {
        $this->supprimerFichier($club);
        $club->setImage(null);
    }

    private function supprimerFichier(Club $club): void
    {
        if ($club->getImage() !== null) {
            $this->filesystem->remove($this->dossier.'/'.basename($club->getImage()));
        }
    }
}
