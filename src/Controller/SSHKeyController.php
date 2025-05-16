<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SSHKey;
use App\Entity\User;
use App\Form\SSHKeyType;
use App\Repository\SSHKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ssh-key')]
#[IsGranted('ROLE_USER')]
class SSHKeyController extends AbstractController
{
    private const ALLOWED_SORT_FIELDS = [
        'name' => 'name',
        'user' => 'user.email',
        'createdAt' => 'createdAt',
        'updatedAt' => 'updatedAt',
    ];

    public function __construct(
        private readonly SSHKeyRepository $sshKeyRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/index', name: 'app_ssh_key_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException('User not found or not authenticated.');
        }

        $sortByInput = $request->query->get('sort_by', 'createdAt');
        $sortDirectionInput = $request->query->get('sort_direction', 'DESC');

        $sortBy = self::ALLOWED_SORT_FIELDS[$sortByInput] ?? self::ALLOWED_SORT_FIELDS['createdAt'];
        $sortDirection = strtoupper($sortDirectionInput) === 'ASC' ? 'ASC' : 'DESC';

        $userToFilter = null;
        if (!$this->isGranted('ROLE_ADMIN')) {
            $userToFilter = $currentUser;
        }

        $keys = $this->sshKeyRepository->findWithSorting($userToFilter, $sortBy, $sortDirection);

        return $this->render('pages/ssh_key/index.html.twig', [
            'keys' => $keys,
            'current_sort_by' => $sortByInput, // Pass the input key for link generation
            'current_sort_direction' => $sortDirection,
        ]);
    }

    #[Route('/new', name: 'app_ssh_key_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Expected instance of User');
        }

        $key = new SSHKey();
        $key->setUser($user);

        $form = $this->createForm(SSHKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($key);
            $this->entityManager->flush();

            $this->addFlash('success', 'ssh_key.flash.created_successfully');

            return $this->redirectToRoute('app_ssh_key_index');
        }

        return $this->render('pages/ssh_key/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ssh_key_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        SSHKey $key,
    ): Response {
        $form = $this->createForm(SSHKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'ssh_key.flash.updated_successfully');

            return $this->redirectToRoute('app_ssh_key_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/ssh_key/edit.html.twig', [
            'key' => $key,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ssh_key_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        SSHKey $key,
    ): Response {
        $submittedToken = $request->request->get('_token');

        // CSRF token check
        if (!$this->isCsrfTokenValid('delete' . $key->getId(), $submittedToken)) {
            $this->addFlash('error', 'common.invalid_csrf_token');

            // Return early if the token is invalid
            return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($key);
        $this->entityManager->flush();

        $this->addFlash('success', 'ssh_key.flash.deleted_successfully');

        // Redirect after successful deletion
        return $this->redirectToRoute('app_ssh_key_index', [], Response::HTTP_SEE_OTHER);
    }
}
