<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\HostConnectionStatus;
use App\Repository\CategoryRepository;
use App\Repository\HostRepository;
use App\Repository\SSHKeyRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly SSHKeyRepository $sshKeyRepository,
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $hostStatusCounts = [
            HostConnectionStatus::SUCCESSFUL->value => $this->hostRepository->count(['connectionStatus' => HostConnectionStatus::SUCCESSFUL]),
            HostConnectionStatus::FAILED->value => $this->hostRepository->count(['connectionStatus' => HostConnectionStatus::FAILED]),
            HostConnectionStatus::CHECKING->value => $this->hostRepository->count(['connectionStatus' => HostConnectionStatus::CHECKING]),
            HostConnectionStatus::UNKNOWN->value => $this->hostRepository->count(['connectionStatus' => HostConnectionStatus::UNKNOWN]),
        ];

        $totalHosts = $this->hostRepository->count([]);
        $classifiedHosts = array_sum($hostStatusCounts);

        if ($totalHosts > $classifiedHosts) {
            $hostStatusCounts[HostConnectionStatus::UNKNOWN->value] += $totalHosts - $classifiedHosts;
        }

        $overview = [
            'hosts' => $totalHosts,
            'keys' => $this->sshKeyRepository->count([]),
            'users' => $this->userRepository->count([]),
            'categories' => $this->categoryRepository->count([]),
        ];

        $successRate = $totalHosts > 0 ? round(($hostStatusCounts[HostConnectionStatus::SUCCESSFUL->value] / $totalHosts) * 100) : null;

        return $this->render('pages/dashboard/index.html.twig', [
            'overview' => $overview,
            'hostStatusCounts' => $hostStatusCounts,
            'successRate' => $successRate,
        ]);
    }
}
