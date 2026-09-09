<?php

namespace App\Http;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

final class InfiniteListResponder
{
    public const PAGE_SIZE = 25;

    public function __construct(private readonly Environment $twig)
    {
    }

    public function paginate(PaginatorInterface $paginator, mixed $query, Request $request, int $pageSize = self::PAGE_SIZE): PaginationInterface
    {
        return $paginator->paginate($query, $request->query->getInt('page', 1), $pageSize);
    }

    public function isPartial(Request $request): bool
    {
        return $request->query->getBoolean('partial');
    }

    public function lastPage(PaginationInterface $pagination): int
    {
        $perPage = max(1, $pagination->getItemNumberPerPage());

        return max(1, (int) ceil($pagination->getTotalItemCount() / $perPage));
    }

    public function json(
        PaginationInterface $pagination,
        string $rowsTemplate,
        string $cardsTemplate,
        string $itemsKey,
        array $context = [],
    ): JsonResponse {
        $vars = array_merge($context, [$itemsKey => $pagination, 'empty' => false]);

        return new JsonResponse([
            'rows' => $this->twig->render($rowsTemplate, $vars),
            'cards' => $this->twig->render($cardsTemplate, $vars),
            'page' => $pagination->getCurrentPageNumber(),
            'lastPage' => $this->lastPage($pagination),
            'total' => $pagination->getTotalItemCount(),
        ]);
    }
}
