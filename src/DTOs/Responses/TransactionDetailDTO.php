<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

use Rmirandasv\Wompi\Enums\ResultadoTransaccion;

/**
 * The detail of a purchase transaction.
 *
 * @property string|null $idTransaccion
 * @property int|null $resultadoTransaccion
 * @property bool|null $esAprobada
 * @property bool|null $esReal
 * @property float|int|null $monto
 * @property float|int|null $montoOriginal
 * @property int|null $formaPago
 * @property string|null $codigoAutorizacion
 * @property string|null $mensaje
 * @property string|null $fechaTransaccion
 * @property string|null $idExterno
 * @property string|null $moduloUtilizado
 * @property string|null $nombreCliente
 * @property string|null $apellidoCliente
 * @property string|null $paisCliente
 * @property string|null $regionCliente
 * @property array<string, string>|null $datosAdicionales
 */
class TransactionDetailDTO extends WompiResponseDTO
{
    /**
     * The transaction result as an enum.
     *
     * Wompi documents the result as an integer enumeration, so the enum
     * carries the raw value. Use isApproved() to know whether the
     * transaction was approved.
     */
    public function resultEnum(): ?ResultadoTransaccion
    {
        return ResultadoTransaccion::fromRaw($this->data['resultadoTransaccion'] ?? null);
    }

    /**
     * Whether the transaction was approved.
     */
    public function isApproved(): bool
    {
        $approved = $this->data['esAprobada'] ?? null;

        return is_bool($approved) ? $approved : false;
    }

    /**
     * Whether the transaction was a real charge.
     *
     * A test transaction was never billed to the customer.
     */
    public function isProduction(): bool
    {
        $real = $this->data['esReal'] ?? null;

        return is_bool($real) ? $real : true;
    }

    /**
     * Whether the payment can be considered successful: approved and real.
     */
    public function isSuccessful(): bool
    {
        return $this->isApproved() && $this->isProduction();
    }
}
