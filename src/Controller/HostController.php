<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Host;
use App\Form\HostType;
use App\Repository\HostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/host')]
class HostController extends AbstractController
{
    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_host_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/host/index.html.twig', [
            'hosts' => $this->hostRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_host_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $host = new Host();
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($host);
            $this->entityManager->flush();

            $this->addFlash('success', 'host.created_successfully');

            return $this->redirectToRoute('app_host_index');
        }

        return $this->render('pages/host/new.html.twig', [
            'form' => $form,
        ]);
    }
}
