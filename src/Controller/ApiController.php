<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\HostRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
#[IsGranted('ROLE_ADMIN')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly HostRepository $hostRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Parses and validates excluded IDs from the request.
     * The 'excluded_ids' query parameter can be a single ID or an array of IDs.
     * e.g., /api/hosts/search?query=server&excluded_ids=1&excluded_ids=2
     * or /api/hosts/search?query=server&excluded_ids=3.
     *
     * @param Request $request the HTTP request object
     *
     * @return int[] an array of positive integer IDs
     */
    private function parseExcludedIds(Request $request): array
    {
        // $request->query->all('excluded_ids') correctly returns an array of strings
        // if 'excluded_ids' is present (e.g., ?excluded_ids=1&excluded_ids=2 or ?excluded_ids[]=1&excluded_ids[]=2)
        // or an empty array if 'excluded_ids' is not in the query string.
        $excludedIdsStrings = $request->query->all('excluded_ids');
        $excludedIds = [];

        foreach ($excludedIdsStrings as $idStr) {
            // filter_var can accept mixed types, but here we expect strings from query parameters.
            // If $idStr is not a string that can be converted to an int, filter_var will return false.
            $id = filter_var($idStr, FILTER_VALIDATE_INT);
            // Ensure the ID is a positive integer.
            if ($id !== false && $id > 0) {
                $excludedIds[] = $id;
            }
        }

        return $excludedIds;
    }

    #[Route('/hosts/search', name: 'api_hosts_search', methods: ['GET'])]
    public function searchHosts(Request $request): JsonResponse
    {
        $query = $request->query->get('query', '');
        $excludedIds = $this->parseExcludedIds($request);

        $hosts = $this->hostRepository->findByNameOrHostnameLike((string) $query, $excludedIds);
        $data = [];
        foreach ($hosts as $host) {
            $data[] = [
                'id' => $host->getId(),
                'name' => $host->getName() . ' (' . $host->getHostname() . ')',
            ];
        }

        return $this->json($data);
    }

    #[Route('/users/search', name: 'api_users_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('query', '');
        $excludedIds = $this->parseExcludedIds($request);

        $users = $this->userRepository->findByEmailLike((string) $query, $excludedIds);
        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'name' => $user->getEmail(),
            ];
        }

        return $this->json($data);
    }
}
