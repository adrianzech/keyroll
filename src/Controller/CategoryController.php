<?php

declare(strict_types=1);

namespace App\Controller;

use App\DataTable\CategoryDataTableType;
use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Kreyu\Bundle\DataTableBundle\DataTableFactoryAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/category')]
#[IsGranted('ROLE_ADMIN')]
class CategoryController extends AbstractController
{
    use DataTableFactoryAwareTrait;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_category_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $queryBuilder = $this->categoryRepository->createQueryBuilder('user');

        $dataTable = $this->createDataTable(CategoryDataTableType::class, $queryBuilder);
        $dataTable->handleRequest($request);

        if ($dataTable->isExporting()) {
            return $this->file($dataTable->export());
        }

        return $this->render('pages/category/index.html.twig', [
            'categories' => $dataTable->createView(),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($category);
            $this->entityManager->flush();

            $this->addFlash('success', 'entity.category.flash.created');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'entity.category.flash.updated');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete', $submittedToken)) {
            $this->addFlash('error', 'common.feedback.invalid_csrf_token');

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        // Check if category is in use before deleting
        if (!$category->getHosts()->isEmpty() || !$category->getUsers()->isEmpty()) {
            $this->addFlash('warning', 'entity.category.alert.cannot_delete_in_use');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'entity.category.flash.deleted');

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
