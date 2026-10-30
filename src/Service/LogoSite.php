<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Logo du site (D62) : fichier dans le dossier app.dossier_images_site (public/uploads/site), nom aléatoire, gardé dans
 * ParametresSite::logo. Le type réel (PNG ou WebP) a été vérifié par le formulaire.
 */
final class LogoSite
{
    public function __construct(
        #[Autowire('%app.dossier_images_site%')]
        private readonly string $dossier,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** @return string le nom du fichier, à enregistrer dans ParametresSite::logo */
    public function ajouter(UploadedFile $fichier): string
    {
        $nom = sprintf('logo-%s.%s', bin2hex(random_bytes(8)), $fichier->getMimeType() === 'image/webp' ? 'webp' : 'png');
        $this->filesystem->mkdir($this->dossier);
        $fichier->move($this->dossier, $nom);

        return $nom;
    }

    /** Appelé une fois la base à jour ; un fichier resté dans le dossier ne gêne personne : l'échec n'est pas signalé. */
    public function supprimer(?string $nom): void
    {
        if ($nom === null) {
            return;
        }
        try {
            $this->filesystem->remove($this->dossier.'/'.basename($nom));
        } catch (IOException) {
        }
    }
}
