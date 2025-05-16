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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/host')]
#[IsGranted('ROLE_USER')]
class HostController extends AbstractController
{
    private const ALLOWED_SORT_FIELDS = [
        'name' => 'name',
        'hostname' => 'hostname',
        'port' => 'port',
        'username' => 'username',
        'connectionStatus' => 'connectionStatus',
        'createdAt' => 'createdAt',
        'updatedAt' => 'updatedAt',
    ];

    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_host_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sortByInput = $request->query->get('sort_by', 'name');
        $sortDirectionInput = $request->query->get('sort_direction', 'ASC');

        $sortBy = self::ALLOWED_SORT_FIELDS[$sortByInput] ?? self::ALLOWED_SORT_FIELDS['name'];

        $sortDirection = strtoupper($sortDirectionInput) === 'DESC' ? 'DESC' : 'ASC';

        $hosts = $this->hostRepository->findWithSorting($sortBy, $sortDirection);

        return $this->render('pages/host/index.html.twig', [
            'hosts' => $hosts,
            'current_sort_by' => $sortByInput,
            'current_sort_direction' => $sortDirection,
        ]);
    }

    #[Route('/new', name: 'app_host_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
    ): Response {
        $host = new Host();
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($host);
            $this->entityManager->flush();

            $this->addFlash('success', 'host.flash.created_successfully');

            return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/host/new.html.twig', [
            'host' => $host,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_host_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        Host $host,
    ): Response {
        $form = $this->createForm(HostType::class, $host);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'host.flash.updated_successfully');

            return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/host/edit.html.twig', [
            'host' => $host,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_host_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Host $host,
    ): Response {
        $submittedToken = $request->request->get('_token');

        // CSRF token check
        if (!$this->isCsrfTokenValid('delete' . $host->getId(), $submittedToken)) {
            $this->addFlash('error', 'common.invalid_csrf_token');

            // Return early if the token is invalid
            return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($host);
        $this->entityManager->flush();

        $this->addFlash('success', 'host.flash.deleted_successfully');

        // Redirect after successful deletion
        return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
    }
}
