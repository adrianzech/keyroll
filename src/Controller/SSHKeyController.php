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

#[Route('/ssh-key')]
// #[IsGranted('ROLE_USER')]
class SSHKeyController extends AbstractController
{
    public function __construct(
        private readonly SSHKeyRepository $sshKeyRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/index', name: 'app_ssh_key_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/ssh_key/index.html.twig', [
            'keys' => $this->sshKeyRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_ssh_key_new', methods: ['GET', 'POST'])]
    // #[IsGranted('ROLE_ADMIN')]
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

            $this->addFlash('success', 'key.created_successfully');

            return $this->redirectToRoute('app_ssh_key_index');
        }

        return $this->render('pages/ssh_key/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ssh_key_edit', methods: ['GET', 'POST'])]
    // #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        SSHKey $key,
    ): Response {
        $form = $this->createForm(SSHKeyType::class, $key);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'key.updated_successfully');

            return $this->redirectToRoute('app_ssh_key_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/ssh_key/edit.html.twig', [
            'host' => $key,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ssh_key_delete', methods: ['POST'])]
    // #[IsGranted('ROLE_ADMIN')]
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

        $this->addFlash('success', 'host.deleted_successfully');

        // Redirect after successful deletion
        return $this->redirectToRoute('app_host_index', [], Response::HTTP_SEE_OTHER);
    }
}
