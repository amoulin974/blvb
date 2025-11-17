<?php

namespace App\Controller\Admin;
use App\Entity\Poule;
use App\Entity\Saison;
use App\Form\PouleType;
use App\Form\Type\SaisonType;
use App\Repository\SaisonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/saison', name: 'admin_saison_')]
final class SaisonController extends AbstractController
{
    #[Route('/saison', name: 'index', methods: ['GET'])]
    public function index(SaisonRepository $saisonRepository): Response
    {
        return $this->render('admin/saison/index.html.twig', [
            'saisons' => $saisonRepository->findAll(),
        ]);
    }



    //Ajoute une saison
    //Attention au EventListener/SaisonListener qui créé les phases et les poules automatiquement
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CacheInterface $cache): Response
    {
        // creates a saison object
        $saison = new Saison();

        //Créer le formulaire et lui envoyer l'objet saison
        $form = $this->createForm(SaisonType::class, $saison);


        //Si le formulaire est soumis et valide
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $saison = $form->getData();
            $em->persist($saison);
            $em->flush();
            $cache->delete('saisons_all');
            return $this->redirectToRoute('admin_saison_show', [
                'id' => $saison->getId(),
            ]);
        }

        return $this->render('admin/saison/new.html.twig', [
            'form' => $form,
            'saison' => $saison,
        ]);

    }


    //Supprime une saison
    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Saison $saison, EntityManagerInterface $entityManager, CacheInterface $cache): Response
    {
        if ($this->isCsrfTokenValid('delete'.$saison->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($saison);
            $entityManager->flush();
            $cache->delete('saisons_all');
        }

        return $this->redirectToRoute('admin_saison_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Saison $saison, EntityManagerInterface $entityManager, CacheInterface $cache): Response
    {
        $form = $this->createForm(SaisonType::class, $saison);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $cache->delete('saisons_all');

            return $this->redirectToRoute('admin_saison_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/saison/edit.html.twig', [
            'saison' => $saison,
            'form' => $form,
        ]);
    }

    //défini une saison comme favorite
    #[Route('/{id}/favorite/', name: 'favorite', methods: ['GET', 'POST'])]
    public function favorite(Request $request, EntityManagerInterface $em, $id, CacheInterface $cache): Response
    {
        $saisons=$em->getRepository(Saison::class)->findAll();
        foreach ($saisons as $saison) {
            if ($saison->getId()==$id){
                $saison->setFavori(1);
                }else{
                $saison->setFavori(0);
            }
            $em->persist($saison);
            $em->flush();
            $cache->delete('saisons_all');
        }
        return $this->redirectToRoute('admin_saison_index');
    }




    //Affiche une saison
    //TODO ajouter des ancres pour qu'après la création des journées ou des matchs on arrive directement sur la bonne poule
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Saison $saison): Response
    {
        return $this->render('admin/saison/show.html.twig', [
            'saison' => $saison,
        ]);
    }
}
