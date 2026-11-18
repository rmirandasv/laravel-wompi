<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * The response of a purchase transaction (3DS or tokenized).
 *
 * @property string|null $idTransaccion
 * @property bool|null $esReal
 * @property bool|null $esAprobada
 * @property string|null $codigoAutorizacion
 * @property string|null $mensaje
 * @property float|int|null $monto
 * @property string|null $urlCompletarPago3Ds
 * @property string|null $idExterno
 */
class TransactionResponseDTO extends WompiResponseDTO
{
}
