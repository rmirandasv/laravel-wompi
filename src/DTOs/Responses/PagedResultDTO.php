<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * A paginated Wompi collection.
 *
 * @property int|null $paginaActual
 * @property int|null $cantidadPorPagina
 * @property int|null $totalDeElementos
 * @property int|null $totalPaginas
 * @property array<int, mixed>|null $resultado
 */
class PagedResultDTO extends WompiResponseDTO
{
    /**
     * The items of the current page.
     *
     * @return array<int, mixed>
     */
    public function items(): array
    {
        $items = $this->data['resultado'] ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * The total number of items across every page.
     */
    public function total(): int
    {
        return (int) ($this->data['totalDeElementos'] ?? 0);
    }

    /**
     * The total number of pages.
     */
    public function totalPages(): int
    {
        return (int) ($this->data['totalPaginas'] ?? 0);
    }

    /**
     * The current page number.
     */
    public function currentPage(): int
    {
        return (int) ($this->data['paginaActual'] ?? 1);
    }
}
